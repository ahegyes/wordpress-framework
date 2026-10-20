<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Core\Permissions;

use DeepWebSolutions\Framework\Permissions\CapabilityGrants;
use DeepWebSolutions\Framework\Shared\Exception\InvalidArgumentException;
use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesStorageTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger as PsrAbstractLogger;

final class GrantsLogger extends PsrAbstractLogger {
	public array $records = array();

	#[\Override]
	public function log( mixed $level, string|\Stringable $message, array $context = array() ): void {
		$this->records[] = array( $level, (string) $message, $context );
	}
}

#[CoversClass( CapabilityGrants::class )]
final class CapabilityGrantsTest extends TestCase {
	use CreatesUsersTrait;
	use IsolatesHooksTrait;
	use IsolatesStorageTrait;

	protected GrantsLogger $logger;

	#[\Override]
	protected function setUp(): void {
		$this->logger = new GrantsLogger();
	}

	#[\Override]
	protected function tearDown(): void {
		// The roles row is shared by every test, so each test's roles and capabilities leave it.
		$this->next_request();
		foreach ( \wp_roles()->roles as $role => $data ) {
			if ( \str_starts_with( $role, $this->prefix ) ) {
				\remove_role( $role );
				continue;
			}
			foreach ( \array_keys( $data['capabilities'] ) as $cap ) {
				if ( \str_starts_with( (string) $cap, $this->prefix ) ) {
					\wp_roles()->remove_cap( $role, (string) $cap );
				}
			}
		}
	}

	public static function invalid_constructions(): array {
		$cap = array( 'acme_cap' );

		return array(
			'empty-option'      => array( '', array( 'acme_role' => $cap ) ),
			'padded-option'     => array( ' acme_grants', array( 'acme_role' => $cap ) ),
			'overlong-option'   => array( \str_repeat( 'a', 192 ), array( 'acme_role' => $cap ) ),
			'empty-role'        => array( 'acme_grants', array( '' => $cap ) ),
			'numeric-role'      => array( 'acme_grants', array( 7 => $cap ) ),
			'caps-not-an-array' => array( 'acme_grants', array( 'acme_role' => 'acme_cap' ) ),
			'caps-not-a-list'   => array( 'acme_grants', array( 'acme_role' => array( 'a' => 'acme_cap' ) ) ),
			'empty-cap'         => array( 'acme_grants', array( 'acme_role' => array( '' ) ) ),
			'non-string-cap'    => array( 'acme_grants', array( 'acme_role' => array( 1 ) ) ),
		);
	}

	#[DataProvider( 'invalid_constructions' )]
	public function test_an_invalid_option_or_grants_map_throws_on_construction( string $option, array $grants ): void {
		$this->expectException( InvalidArgumentException::class );

		new CapabilityGrants( $option, $grants, $this->logger );
	}

	public function test_construction_reads_writes_and_hooks_nothing(): void {
		global $wpdb;

		$queries = $wpdb->num_queries;
		$grants  = $this->grants( array( $this->role( 'editor' ) => array( $this->cap( 'a' ) ) ) );

		self::assertSame( $queries, $wpdb->num_queries );
		self::assertSame( array(), $this->hooks_of( $grants ) );
	}

	public function test_registering_hooks_runs_the_grants_on_wp_loaded_and_watches_role_deletions_at_10(): void {
		$grants = $this->grants( array( $this->role( 'editor' ) => array( $this->cap( 'a' ) ) ) );

		$grants->register_hooks();

		self::assertSame( array( 'updated_option@10/3:forget_deleted_roles', 'wp_loaded@10/1:run' ), $this->hooks_of( $grants ) );
	}

	public function test_the_first_run_grants_each_declared_pair_to_its_role_and_ledgers_it(): void {
		$editor   = $this->add_role( 'editor' );
		$author   = $this->add_role( 'author' );
		$user     = $this->make_user( 'acme_grants_user', $author );
		$declared = array(
			$editor => array( $this->cap( 'a' ), $this->cap( 'b' ) ),
			$author => array( $this->cap( 'a' ) ),
		);

		$this->grants( $declared )->run();

		self::assertSame(
			array(
				$this->cap( 'a' ) => true,
				$this->cap( 'b' ) => true,
			),
			$this->stored_caps( $editor )
		);
		self::assertSame( array( $this->cap( 'a' ) => true ), $this->stored_caps( $author ) );
		self::assertTrue( ( new \WP_User( $user ) )->has_cap( $this->cap( 'a' ) ) );
		self::assertEquals( $declared, $this->ledger() );
	}

	public function test_a_run_with_nothing_changed_makes_no_query(): void {
		global $wpdb;

		$declared = array( $this->add_role( 'editor' ) => array( $this->cap( 'a' ) ) );
		$this->grants( $declared )->run();
		$this->next_request();
		\wp_load_alloptions();

		$queries = $wpdb->num_queries;
		$this->grants( $declared )->run();

		self::assertSame( $queries, $wpdb->num_queries );
	}

	public function test_a_missing_role_is_skipped_until_a_later_request_adds_it(): void {
		$later    = $this->role( 'later' );
		$editor   = $this->add_role( 'editor' );
		$declared = array(
			$later  => array( $this->cap( 'a' ) ),
			$editor => array( $this->cap( 'a' ) ),
		);

		$this->grants( $declared )->run();

		self::assertArrayNotHasKey( $later, $this->stored_roles() );
		self::assertEquals( array( $editor => array( $this->cap( 'a' ) ) ), $this->ledger() );

		\add_role( $later, 'Later' );
		$this->next_request();
		$this->grants( $declared )->run();

		self::assertSame( array( $this->cap( 'a' ) => true ), $this->stored_caps( $later ) );
		self::assertEquals( $declared, $this->ledger() );
	}

	public function test_a_declared_pair_the_role_already_held_is_never_ledgered_or_revoked(): void {
		$editor = $this->add_role( 'editor', array( $this->cap( 'a' ) => true ) );

		$this->grants( array( $editor => array( $this->cap( 'a' ), $this->cap( 'b' ) ) ) )->run();

		self::assertEquals( array( $editor => array( $this->cap( 'b' ) ) ), $this->ledger() );

		$this->next_request();
		$this->grants( array() )->run();

		self::assertSame( array( $this->cap( 'a' ) => true ), $this->stored_caps( $editor ) );
		self::assertSame( array(), $this->ledger() );
	}

	public function test_a_ledgered_pair_the_declaration_drops_is_revoked_unless_changed_by_hand_and_leaves_the_ledger(): void {
		$editor = $this->add_role( 'editor' );
		$this->grants( array( $editor => array( $this->cap( 'a' ), $this->cap( 'b' ), $this->cap( 'c' ) ) ) )->run();
		\wp_roles()->remove_cap( $editor, $this->cap( 'b' ) );
		\wp_roles()->add_cap( $editor, $this->cap( 'c' ), false );

		$this->next_request();
		$this->grants( array() )->run();

		self::assertSame( array( $this->cap( 'c' ) => false ), $this->stored_caps( $editor ) );
		self::assertSame( array(), $this->ledger() );
	}

	public function test_a_ledgered_pair_whose_role_is_gone_leaves_the_ledger(): void {
		$editor   = $this->add_role( 'editor' );
		$author   = $this->add_role( 'author' );
		$declared = array(
			$editor => array( $this->cap( 'a' ) ),
			$author => array( $this->cap( 'a' ) ),
		);
		$this->grants( $declared )->run();
		\remove_role( $author );

		$this->next_request();
		$this->grants( $declared )->run();

		self::assertEquals( array( $editor => array( $this->cap( 'a' ) ) ), $this->ledger() );
	}

	public function test_a_declared_pair_removed_or_denied_by_hand_stays_so_when_a_later_run_grants_another(): void {
		$editor = $this->add_role( 'editor' );
		$this->grants( array( $editor => array( $this->cap( 'a' ), $this->cap( 'b' ) ) ) )->run();
		\wp_roles()->remove_cap( $editor, $this->cap( 'a' ) );
		\wp_roles()->add_cap( $editor, $this->cap( 'b' ), false );

		$this->next_request();
		$this->grants( array( $editor => array( $this->cap( 'a' ), $this->cap( 'b' ), $this->cap( 'c' ) ) ) )->run();

		self::assertSame(
			array(
				$this->cap( 'b' ) => false,
				$this->cap( 'c' ) => true,
			),
			$this->stored_caps( $editor )
		);
		self::assertEquals( array( $editor => array( $this->cap( 'a' ), $this->cap( 'b' ), $this->cap( 'c' ) ) ), $this->ledger() );
	}

	public function test_a_declared_role_deleted_and_created_again_in_a_later_request_is_granted_again(): void {
		$editor   = $this->add_role( 'editor' );
		$declared = array( $editor => array( $this->cap( 'a' ) ) );
		$this->grants( $declared )->run();
		\remove_role( $editor );
		$this->next_request();
		$this->grants( $declared )->run();

		$this->add_role( 'editor' );
		$this->next_request();
		$this->grants( $declared )->run();

		self::assertSame( array( $this->cap( 'a' ) => true ), $this->stored_caps( $editor ) );
	}

	public function test_a_declared_role_deleted_and_created_again_in_one_request_is_granted_again_on_the_next(): void {
		$editor   = $this->add_role( 'editor' );
		$declared = array( $editor => array( $this->cap( 'a' ) ) );
		$grants   = $this->grants( $declared );
		$grants->register_hooks();
		$grants->run();

		\remove_role( $editor );
		$this->add_role( 'editor' );
		$this->next_request();
		$this->grants( $declared )->run();

		self::assertSame( array( $this->cap( 'a' ) => true ), $this->stored_caps( $editor ) );
	}

	public function test_a_shop_manager_role_reset_by_woocommerce_is_granted_again_on_the_next_request(): void {
		$declared = array( 'shop_manager' => array( $this->cap( 'a' ) ) );
		$grants   = $this->grants( $declared );
		$grants->register_hooks();
		$grants->run();

		\WC_Install::remove_roles();
		\WC_Install::create_roles();

		self::assertArrayNotHasKey( $this->cap( 'a' ), $this->stored_caps( 'shop_manager' ) );

		$this->next_request();
		$this->grants( $declared )->run();

		self::assertTrue( $this->stored_caps( 'shop_manager' )[ $this->cap( 'a' ) ] ?? false );
	}

	public function test_deleting_an_undeclared_role_or_writing_another_option_leaves_the_ledger_row(): void {
		$editor = $this->add_role( 'editor' );
		$other  = $this->add_role( 'other' );
		$grants = $this->grants( array( $editor => array( $this->cap( 'a' ) ) ) );
		$grants->register_hooks();
		$grants->run();
		$row = \get_option( "{$this->prefix}_grants" );

		\remove_role( $other );
		\update_option( "{$this->prefix}_unrelated", array( $editor => array() ) );

		self::assertSame( $row, \get_option( "{$this->prefix}_grants" ) );
	}

	public function test_a_changed_declaration_grants_the_added_pairs_and_revokes_the_dropped_ones_in_one_run(): void {
		$editor = $this->add_role( 'editor' );
		$author = $this->add_role( 'author' );
		$this->grants( array( $editor => array( $this->cap( 'a' ), $this->cap( 'b' ) ) ) )->run();

		$this->next_request();
		$declared = array(
			$editor => array( $this->cap( 'b' ) ),
			$author => array( $this->cap( 'a' ) ),
		);
		$this->grants( $declared )->run();

		self::assertSame( array( $this->cap( 'b' ) => true ), $this->stored_caps( $editor ) );
		self::assertSame( array( $this->cap( 'a' ) => true ), $this->stored_caps( $author ) );
		self::assertEquals( $declared, $this->ledger() );
	}

	protected function grants( array $grants, ?PsrAbstractLogger $logger = null ): CapabilityGrants {
		return new CapabilityGrants( "{$this->prefix}_grants", $grants, $logger ?? $this->logger );
	}

	protected function role( string $name ): string {
		return "{$this->prefix}_$name";
	}

	protected function cap( string $name ): string {
		return "{$this->prefix}_cap_$name";
	}

	protected function add_role( string $name, array $capabilities = array() ): string {
		\add_role( $this->role( $name ), \ucfirst( $name ), $capabilities );

		return $this->role( $name );
	}

	// A new request reads the roles row and the options again, so nothing a test changed stays only in memory.
	protected function next_request(): void {
		\wp_cache_flush();
		\wp_roles()->for_site();
	}

	protected function stored_roles(): array {
		global $wpdb;

		$raw = $wpdb->get_var( $wpdb->prepare( 'SELECT option_value FROM %i WHERE option_name = %s', $wpdb->options, \wp_roles()->role_key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reads past the options cache.

		return (array) \maybe_unserialize( (string) $raw );
	}

	protected function stored_caps( string $role ): array {
		return $this->stored_roles()[ $role ]['capabilities'] ?? array();
	}

	protected function ledger(): array {
		return \get_option( "{$this->prefix}_grants" )['grants'] ?? array();
	}

	protected function hooks_of( object $component ): array {
		$hooks = array();
		foreach ( $GLOBALS['wp_filter'] as $name => $hook ) {
			foreach ( $hook->callbacks as $priority => $callbacks ) {
				foreach ( $callbacks as $callback ) {
					if ( \is_array( $callback['function'] ) && $component === $callback['function'][0] ) {
						$hooks[] = "$name@$priority/{$callback['accepted_args']}:{$callback['function'][1]}";
					}
				}
			}
		}
		\sort( $hooks );

		return $hooks;
	}
}
