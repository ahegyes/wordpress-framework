<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Core\AdminNotice\Stores;

use DeepWebSolutions\Framework\Core\AdminNotice\Notice;
use DeepWebSolutions\Framework\Core\AdminNotice\NoticeContext;
use DeepWebSolutions\Framework\Core\AdminNotice\NoticeScope;
use DeepWebSolutions\Framework\Core\AdminNotice\StoredNotice;
use DeepWebSolutions\Framework\Core\AdminNotice\Stores\NoticeRecords;
use DeepWebSolutions\Framework\Core\AdminNotice\Stores\OptionNoticeStore;
use DeepWebSolutions\Framework\Shared\Exception\RuntimeException;
use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesSitesTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\FindsNoticesTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\InterceptsQueriesTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesStorageTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass( OptionNoticeStore::class )]
final class OptionNoticeStoreTest extends TestCase {
	use CreatesSitesTrait;
	use FindsNoticesTrait;
	use InterceptsQueriesTrait;
	use IsolatesHooksTrait;
	use IsolatesStorageTrait;

	#[\Override]
	protected function tearDown(): void {
		while ( \is_multisite() && \ms_is_switched() ) {
			\restore_current_blog();
		}
	}

	public function test_each_notice_is_one_option_that_is_not_autoloaded(): void {
		global $wpdb;
		$store  = new OptionNoticeStore( $this->prefix );
		$site   = $store->save( $this->notice( NoticeScope::Site, 'one', "Can't \\ \"save\"." ), NoticeContext::current( NoticeScope::Site ) );
		$global = $store->save( $this->notice( NoticeScope::Network, 'one' ), NoticeContext::current( NoticeScope::Network ) );

		self::assertSame(
			array(
				"{$this->prefix}_network_notice_one" => array( NoticeRecords::encode( $global ), 'off' ),
				"{$this->prefix}_notice_one"         => array( NoticeRecords::encode( $site ), 'off' ),
			),
			$this->rows( $wpdb->options )
		);
	}

	public function test_on_a_single_site_a_network_notice_is_apart_from_a_site_notice_of_the_same_id(): void {
		$store = new OptionNoticeStore( $this->prefix );
		$store->save( $this->notice( NoticeScope::Site, 'one', 'Site.' ), NoticeContext::current( NoticeScope::Site ) );
		$store->save( $this->notice( NoticeScope::Network, 'one', 'Network.' ), NoticeContext::current( NoticeScope::Network ) );

		self::assertSame( 'Site.', self::find( $store, 'one', NoticeContext::current( NoticeScope::Site ) )?->notice->message );
		self::assertSame( 'Network.', self::find( $store, 'one', NoticeContext::current( NoticeScope::Network ) )?->notice->message );
	}

	public function test_a_failed_write_throws_and_keeps_the_previous_notice(): void {
		$store    = new OptionNoticeStore( $this->prefix );
		$context  = NoticeContext::current( NoticeScope::Site );
		$previous = $store->save( $this->notice( NoticeScope::Site, 'one' ), $context );
		$this->fail_queries( 'ON DUPLICATE KEY UPDATE' );

		try {
			$store->save( $this->notice( NoticeScope::Site, 'one', 'Again.' ), $context );
			self::fail( 'The failed write did not throw.' );
		} catch ( RuntimeException ) {
			\remove_all_filters( 'query' );
			self::assertEquals( $previous, self::find( $store, 'one', $context ) );
		}
	}

	public static function failing_operations(): array {
		return array(
			'all-read'       => array( "LIKE '", static fn ( OptionNoticeStore $store, StoredNotice $record ): mixed => $store->all( $record->context ) ),
			'consume-read'   => array( 'SELECT option_value FROM', static fn ( OptionNoticeStore $store, StoredNotice $record ): mixed => $store->consume( $record ) ),
			'consume-delete' => array( 'CAST( option_value AS BINARY )', static fn ( OptionNoticeStore $store, StoredNotice $record ): mixed => $store->consume( $record ) ),
			'remove'         => array( 'DELETE FROM', static fn ( OptionNoticeStore $store, StoredNotice $record ): mixed => $store->remove( 'one', $record->context ) ),
		);
	}

	#[DataProvider( 'failing_operations' )]
	public function test_a_failed_query_throws( string $needle, \Closure $operation ): void {
		$store  = new OptionNoticeStore( $this->prefix );
		$record = $store->save( $this->notice( NoticeScope::Site, 'one' ), NoticeContext::current( NoticeScope::Site ) );
		$this->fail_queries( $needle );

		$this->expectException( RuntimeException::class );

		$operation( $store, $record );
	}

	public function test_an_expired_notice_whose_delete_fails_throws(): void {
		$store   = new OptionNoticeStore( $this->prefix );
		$context = NoticeContext::current( NoticeScope::Site );
		$store->save( $this->notice( NoticeScope::Site, 'one', expires_at: \time() - 1 ), $context );
		$this->fail_queries( 'CAST( option_value AS BINARY )' );

		$this->expectException( RuntimeException::class );

		$store->all( $context );
	}

	public function test_rows_that_are_not_valid_site_notices_of_their_option_are_skipped_and_kept(): void {
		global $wpdb;
		$context = NoticeContext::current( NoticeScope::Site );
		$valid   = NoticeRecords::encode( new StoredNotice( $this->notice( NoticeScope::Site, 'one' ), $context, 1_000, 'gen' ) );
		$rows    = array(
			"{$this->prefix}_notice_garbage" => 'garbage',
			"{$this->prefix}_notice_later"   => array( 'v' => 2 ) + $valid,
			"{$this->prefix}_notice_network" => array( 'scope' => 'network' ) + $valid,
			"{$this->prefix}_notice_other"   => $valid,
		);
		foreach ( $rows as $name => $row ) {
			\add_option( $name, $row, '', false );
		}

		self::assertSame( array(), ( new OptionNoticeStore( $this->prefix ) )->all( $context ) );
		self::assertSame( \array_keys( $rows ), \array_keys( $this->rows( $wpdb->options ) ) );
	}

	public function test_a_plugin_whose_prefix_extends_this_ones_keeps_its_notices_apart(): void {
		$context = NoticeContext::current( NoticeScope::Site );
		$longer  = new OptionNoticeStore( "{$this->prefix}_notice" );
		$longer->save( $this->notice( NoticeScope::Site, 'one' ), $context );

		self::assertSame( array(), ( new OptionNoticeStore( $this->prefix ) )->all( $context ) );
		self::assertCount( 1, $longer->all( $context ) );
	}

	#[Group( 'multisite' )]
	public function test_a_site_notice_lives_in_its_sites_options_and_shows_only_there(): void {
		global $wpdb;
		$site_id = $this->make_site( 'acme-notices' );
		$store   = new OptionNoticeStore( $this->prefix );
		\switch_to_blog( $site_id );
		$on_site = NoticeContext::current( NoticeScope::Site );
		\restore_current_blog();

		$store->save( $this->notice( NoticeScope::Site, 'one' ), $on_site );

		self::assertSame( array( "{$this->prefix}_notice_one" ), \array_keys( $this->rows( $wpdb->get_blog_prefix( $site_id ) . 'options' ) ) );
		self::assertSame( array(), $this->rows( $wpdb->options ) );
		self::assertSame( array(), $store->all( NoticeContext::current( NoticeScope::Site ) ) );
		self::assertSame( 'one', self::find( $store, 'one', $on_site )?->notice->id );
		self::assertSame( \get_main_site_id(), \get_current_blog_id() );
	}

	#[Group( 'multisite' )]
	public function test_a_network_notice_lives_in_the_main_sites_options_and_shows_on_every_site(): void {
		global $wpdb;
		$site_id = $this->make_site( 'acme-notices' );
		\switch_to_blog( $site_id );
		$record = ( new OptionNoticeStore( $this->prefix ) )->save( $this->notice( NoticeScope::Network, 'one' ), NoticeContext::current( NoticeScope::Network ) );
		$there  = ( new OptionNoticeStore( $this->prefix ) )->all( NoticeContext::current( NoticeScope::Network ) );
		$still  = \get_current_blog_id();
		\restore_current_blog();

		self::assertSame( $site_id, $still );
		self::assertEquals( array( $record ), $there );
		self::assertEquals( array( $record ), ( new OptionNoticeStore( $this->prefix ) )->all( NoticeContext::current( NoticeScope::Network ) ) );
		self::assertSame( array( "{$this->prefix}_network_notice_one" ), \array_keys( $this->rows( $wpdb->get_blog_prefix( \get_main_site_id() ) . 'options' ) ) );
		self::assertSame( array(), $this->rows( $wpdb->get_blog_prefix( $site_id ) . 'options' ) );
	}

	#[Group( 'multisite' )]
	public function test_a_network_notice_lives_in_the_options_of_the_networks_main_site_whichever_it_is(): void {
		global $wpdb;
		$site_id = $this->make_site( 'acme-notices' );
		\add_filter( 'pre_get_main_site_id', static fn (): int => $site_id );

		( new OptionNoticeStore( $this->prefix ) )->save( $this->notice( NoticeScope::Network, 'one' ), NoticeContext::current( NoticeScope::Network ) );

		self::assertSame( array( "{$this->prefix}_network_notice_one" ), \array_keys( $this->rows( $wpdb->get_blog_prefix( $site_id ) . 'options' ) ) );
		self::assertSame( array(), $this->rows( $wpdb->options ) );
	}

	protected function notice( NoticeScope $scope, string $id, string $message = 'Hi.', ?int $expires_at = null ): Notice {
		return new Notice( $id, $message, scope: $scope, capability: 'manage_options', expires_at: $expires_at );
	}

	protected function rows( string $table ): array {
		global $wpdb;

		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT option_name, option_value, autoload FROM %i WHERE option_name LIKE %s ORDER BY option_name', $table, $wpdb->esc_like( $this->prefix ) . '%' ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Inspects the stored rows.
		$out  = array();
		foreach ( $rows as $row ) {
			$out[ $row['option_name'] ] = array( \maybe_unserialize( $row['option_value'] ), $row['autoload'] );
		}

		return $out;
	}
}
