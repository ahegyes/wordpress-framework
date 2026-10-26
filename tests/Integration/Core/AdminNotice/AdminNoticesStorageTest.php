<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Core\AdminNotice;

use DeepWebSolutions\Framework\Core\AdminNotice\AdminNotices;
use DeepWebSolutions\Framework\Core\AdminNotice\Notice;
use DeepWebSolutions\Framework\Core\AdminNotice\NoticeScope;
use DeepWebSolutions\Framework\Core\Logging\ErrorLogLogger;
use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesSitesTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesStorageTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass( AdminNotices::class )]
final class AdminNoticesStorageTest extends TestCase {
	use CreatesSitesTrait;
	use CreatesUsersTrait;
	use IsolatesStorageTrait;

	protected int $user_id;
	protected AdminNotices $notices;

	#[\Override]
	protected function setUp(): void {
		$this->user_id = $this->make_user( 'acme_notices_admin', 'administrator' );
		$this->notices = new AdminNotices( $this->prefix, new ErrorLogLogger( 'acme' ) );
		wp_set_current_user( $this->user_id );
	}

	#[\Override]
	protected function tearDown(): void {
		wp_set_current_user( 0 );
		while ( is_multisite() && ms_is_switched() ) {
			restore_current_blog();
		}
	}

	public function test_each_scope_keeps_its_notices_as_one_array_by_id_in_its_home(): void {
		global $wpdb;
		$user = array( $this->notice( 'one' ), $this->notice( 'two' ) );
		$site = $this->notice( 'one', NoticeScope::Site );
		foreach ( array( ...$user, $site ) as $notice ) {
			$this->notices->add( $notice );
		}

		self::assertSame(
			array(
				'one' => $user[0]->to_array(),
				'two' => $user[1]->to_array(),
			),
			$this->user_meta( $wpdb->get_blog_prefix() . "{$this->prefix}_notices" )
		);
		self::assertSame( array( array( 'one' => $site->to_array() ), 'off' ), $this->option_row( "{$this->prefix}_notices" ) );
	}

	public function test_on_a_single_site_network_notices_keep_their_own_option_that_is_not_autoloaded(): void {
		if ( is_multisite() ) {
			self::markTestSkipped( 'A single-site row.' );
		}

		$this->notices->add( $this->notice( 'one', NoticeScope::Site, 'Site.' ) );
		$network = $this->notice( 'one', NoticeScope::Network, 'Network.' );
		$this->notices->add( $network );

		self::assertSame( array( array( 'one' => $network->to_array() ), 'off' ), $this->option_row( "{$this->prefix}_network_notices" ) );
		self::assertSame( array( 'Site.' ), self::messages( $this->notices->get_notices( NoticeScope::Site ) ) );
		self::assertSame( array( 'Network.' ), self::messages( $this->notices->get_notices( NoticeScope::Network ) ) );
	}

	public static function stored_scopes(): array {
		return array(
			'user'    => array( NoticeScope::User ),
			'site'    => array( NoticeScope::Site ),
			'network' => array( NoticeScope::Network ),
		);
	}

	#[DataProvider( 'stored_scopes' )]
	public function test_a_message_with_quotes_and_backslashes_reads_back_unchanged( NoticeScope $scope ): void {
		$this->notices->add( $this->notice( 'one', $scope, "Can't \\ \"save\" <b>this</b>." ) );
		wp_cache_flush();

		self::assertSame( array( "Can't \\ \"save\" <b>this</b>." ), self::messages( $this->notices->get_notices( $scope ) ) );
	}

	#[DataProvider( 'stored_scopes' )]
	public function test_a_notice_added_again_replaces_the_stored_one_in_its_place( NoticeScope $scope ): void {
		$this->notices->add( $this->notice( 'one', $scope, 'One.' ) );
		$this->notices->add( $this->notice( 'two', $scope, 'Two.' ) );

		$this->notices->add( $this->notice( 'one', $scope, 'Again.' ) );

		self::assertSame( array( 'Again.', 'Two.' ), self::messages( $this->notices->get_notices( $scope ) ) );
	}

	public function test_entries_that_are_not_valid_notices_of_the_scope_are_left_out_and_dropped_at_the_next_write(): void {
		$valid = $this->notice( 'one', NoticeScope::Site )->to_array();
		update_option(
			"{$this->prefix}_notices",
			array(
				'garbage' => 'garbage',
				'later'   => array( 'v' => 2 ) + $valid,
				'network' => array( 'scope' => 'network' ) + $valid,
				'expired' => array(
					'id'         => 'expired',
					'expires_at' => \time() - 1,
				) + $valid,
				'one'     => $valid,
			),
			false
		);

		self::assertSame( array( 'one' ), self::ids( $this->notices->get_notices( NoticeScope::Site ) ) );
		self::assertCount( 5, $this->option_row( "{$this->prefix}_notices" )[0] );

		$this->notices->add( $this->notice( 'two', NoticeScope::Site ) );

		self::assertSame( array( 'one', 'two' ), \array_keys( $this->option_row( "{$this->prefix}_notices" )[0] ) );
	}

	public function test_an_entry_is_read_under_its_own_id(): void {
		update_option( "{$this->prefix}_notices", array( 'other' => $this->notice( 'one', NoticeScope::Site )->to_array() ), false );

		$this->notices->remove( 'one', NoticeScope::Site );

		self::assertSame( array(), $this->option_row( "{$this->prefix}_notices" )[0] );
	}

	public function test_a_viewers_hidden_notices_of_a_site_are_one_user_option_keyed_by_scope_and_id(): void {
		global $wpdb;
		foreach ( array( NoticeScope::User, NoticeScope::Site ) as $scope ) {
			$this->notices->add( $this->notice( 'one', $scope, per_viewer: NoticeScope::Site === $scope ) );
		}
		$this->view( 'dashboard' );

		$hidden = $this->user_meta( $wpdb->get_blog_prefix() . "{$this->prefix}_hidden_notices" );

		self::assertSame( array( 'site:one' ), \array_keys( $hidden ) );
		self::assertSame( 'delivered', $hidden['site:one']['state'] );
		self::assertEqualsWithDelta( \time(), $hidden['site:one']['at'], 5 );
		self::assertSame( array(), $this->user_meta( "{$this->prefix}_network_hidden_notices" ) );
	}

	public function test_a_viewers_hidden_network_notices_are_one_network_wide_user_meta_row(): void {
		$this->notices->add( $this->notice( 'one', NoticeScope::Network, per_viewer: true ) );
		$this->view( 'dashboard' );

		self::assertSame( array( 'network:one' ), \array_keys( $this->user_meta( "{$this->prefix}_network_hidden_notices" ) ) );
		self::assertFalse( get_user_option( "{$this->prefix}_hidden_notices", $this->user_id ) );
	}

	#[Group( 'multisite' )]
	public function test_site_and_user_notices_live_under_their_sites_keys(): void {
		global $wpdb;
		$site_id = $this->make_site( 'acme-notices' );
		switch_to_blog( $site_id );
		$this->notices->add( $this->notice( 'one' ) );
		$this->notices->add( $this->notice( 'one', NoticeScope::Site ) );
		restore_current_blog();

		self::assertSame( array( 'one' ), \array_keys( $this->user_meta( $wpdb->get_blog_prefix( $site_id ) . "{$this->prefix}_notices" ) ) );
		self::assertSame( array( 'one' ), \array_keys( (array) get_blog_option( $site_id, "{$this->prefix}_notices" ) ) );
		self::assertSame( array(), $this->notices->get_notices( NoticeScope::User ) );
		self::assertSame( array(), $this->notices->get_notices( NoticeScope::Site ) );
	}

	#[Group( 'multisite' )]
	public function test_network_notices_live_in_the_network_option_whichever_site_adds_them(): void {
		$site_id = $this->make_site( 'acme-notices' );
		switch_to_blog( $site_id );
		$this->notices->add( $this->notice( 'one', NoticeScope::Network ) );
		restore_current_blog();

		self::assertSame( array( 'one' ), \array_keys( (array) get_network_option( null, "{$this->prefix}_network_notices" ) ) );
		self::assertFalse( get_blog_option( $site_id, "{$this->prefix}_network_notices" ) );
		self::assertSame( array( 'one' ), self::ids( $this->notices->get_notices( NoticeScope::Network ) ) );
	}

	protected function notice( string $id, NoticeScope $scope = NoticeScope::User, string $message = 'Hi.', mixed ...$arguments ): Notice {
		$arguments['scope']        = $scope;
		$arguments['capability'] ??= NoticeScope::User === $scope ? null : 'manage_options';

		return new Notice( $id, $message, ...$arguments );
	}

	protected function view( string $screen ): void {
		require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		require_once ABSPATH . 'wp-admin/includes/screen.php';

		set_current_screen( $screen );
		\ob_start();
		$this->notices->render_notices();
		\ob_end_clean();
		$GLOBALS['current_screen'] = null;
	}

	// Reads past the user meta cache, so the row is what the next request reads.
	protected function user_meta( string $key ): array {
		global $wpdb;

		$values = $wpdb->get_col( $wpdb->prepare( 'SELECT meta_value FROM %i WHERE user_id = %d AND meta_key = %s', $wpdb->usermeta, $this->user_id, $key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Inspects the stored row.
		self::assertLessThanOrEqual( 1, \count( $values ) );

		return \is_string( $values[0] ?? null ) ? (array) maybe_unserialize( $values[0] ) : array();
	}

	protected function option_row( string $name ): array {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT option_value, autoload FROM %i WHERE option_name = %s', $wpdb->options, $name ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Inspects the stored row.

		return array( maybe_unserialize( $row['option_value'] ?? '' ), $row['autoload'] ?? null );
	}

	protected static function ids( array $notices ): array {
		return \array_map( static fn ( Notice $notice ): string => $notice->id, $notices );
	}

	protected static function messages( array $notices ): array {
		return \array_map( static fn ( Notice $notice ): string => $notice->message, $notices );
	}
}
