<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Core\Notices;

use DeepWebSolutions\Framework\Notices\Internal\NoticeRecords;
use DeepWebSolutions\Framework\Notices\Internal\UserNoticeStore;
use DeepWebSolutions\Framework\Notices\Notice;
use DeepWebSolutions\Framework\Notices\NoticeContext;
use DeepWebSolutions\Framework\Notices\NoticeScope;
use DeepWebSolutions\Framework\Notices\StoredNotice;
use DeepWebSolutions\Framework\Shared\Exception\LogicException;
use DeepWebSolutions\Framework\Shared\Exception\RuntimeException;
use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesSitesTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesNoticeStorageTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass( UserNoticeStore::class )]
final class UserNoticeStoreTest extends TestCase {
	use CreatesSitesTrait;
	use CreatesUsersTrait;
	use IsolatesHooksTrait;
	use IsolatesNoticeStorageTrait;

	protected int $user_id;

	protected NoticeContext $context;

	#[\Override]
	protected function setUp(): void {
		$this->user_id = $this->make_user( 'acme_notices_admin', 'administrator' );
		$this->context = NoticeContext::current( NoticeScope::User, $this->user_id );
	}

	#[\Override]
	protected function tearDown(): void {
		while ( \is_multisite() && \ms_is_switched() ) {
			\restore_current_blog();
		}
	}

	public function test_each_notice_is_one_user_meta_row_under_the_sites_key(): void {
		global $wpdb;
		$store = new UserNoticeStore( $this->prefix );
		$store->save( new Notice( 'one', "Can't \\ \"save\"." ), $this->context );
		$store->save( new Notice( 'two', 'Two.' ), $this->context );

		$replacement = $store->save( new Notice( 'one', 'Again.' ), $this->context );

		$rows = $this->rows( $wpdb->get_blog_prefix() . "{$this->prefix}_notices" );
		self::assertCount( 2, $rows );
		self::assertSame( array( 'two', 'one' ), \array_column( $rows, 'id' ) );
		self::assertSame( NoticeRecords::encode( $replacement ), $rows[1] );
	}

	public function test_a_message_with_quotes_and_backslashes_is_stored_unchanged(): void {
		$store = new UserNoticeStore( $this->prefix );
		$store->save( new Notice( 'one', "Can't \\ \"save\"." ), $this->context );

		self::assertSame( "Can't \\ \"save\".", ( new UserNoticeStore( $this->prefix ) )->find( 'one', $this->context )?->notice->message );
	}

	public function test_a_failed_insert_throws_and_keeps_the_previous_notice(): void {
		$store    = new UserNoticeStore( $this->prefix );
		$previous = $store->save( new Notice( 'one', 'One.' ), $this->context );
		\add_filter( 'add_user_metadata', '__return_false' );

		try {
			$store->save( new Notice( 'one', 'Again.' ), $this->context );
			self::fail( 'The failed insert did not throw.' );
		} catch ( RuntimeException ) {
			self::assertEquals( $previous, ( new UserNoticeStore( $this->prefix ) )->find( 'one', $this->context ) );
		}
	}

	public static function row_orders(): array {
		return array(
			'newer-row-first' => array( true ),
			'older-row-first' => array( false ),
		);
	}

	#[DataProvider( 'row_orders' )]
	public function test_of_two_rows_for_one_notice_the_newer_is_read_and_the_older_deleted( bool $newer_first ): void {
		global $wpdb;
		$key   = $wpdb->get_blog_prefix() . "{$this->prefix}_notices";
		$older = new StoredNotice( new Notice( 'one', 'Older.' ), $this->context, 1_000, 'gen-older' );
		$newer = new StoredNotice( new Notice( 'one', 'Newer.' ), $this->context, 2_000, 'gen-newer' );
		foreach ( $newer_first ? array( $newer, $older ) : array( $older, $newer ) as $record ) {
			\add_user_meta( $this->user_id, $key, NoticeRecords::encode( $record ) );
		}

		self::assertEquals( array( $newer ), ( new UserNoticeStore( $this->prefix ) )->all( $this->context ) );
		self::assertSame( array( NoticeRecords::encode( $newer ) ), $this->rows( $key ) );
	}

	#[DataProvider( 'row_orders' )]
	public function test_when_the_newer_of_two_rows_has_expired_neither_shows_and_both_are_deleted( bool $newer_first ): void {
		global $wpdb;
		$key   = $wpdb->get_blog_prefix() . "{$this->prefix}_notices";
		$older = new StoredNotice( new Notice( 'one', 'Older.' ), $this->context, 1_000, 'gen-older' );
		$newer = new StoredNotice( new Notice( 'one', 'Newer.', expires_at: \time() - 1 ), $this->context, 2_000, 'gen-newer' );
		foreach ( $newer_first ? array( $newer, $older ) : array( $older, $newer ) as $record ) {
			\add_user_meta( $this->user_id, $key, NoticeRecords::encode( $record ) );
		}

		self::assertSame( array(), ( new UserNoticeStore( $this->prefix ) )->all( $this->context ) );
		self::assertSame( array(), $this->rows( $key ) );
	}

	public function test_rows_that_are_not_valid_user_notices_are_skipped_and_kept(): void {
		global $wpdb;
		$key   = $wpdb->get_blog_prefix() . "{$this->prefix}_notices";
		$valid = NoticeRecords::encode( new StoredNotice( new Notice( 'one', 'One.' ), $this->context, 1_000, 'gen' ) );
		$rows  = array( 'garbage', array( 'v' => 2 ) + $valid, array( 'scope' => 'site' ) + $valid, \array_diff_key( $valid, array( 'gen' => true ) ) );
		foreach ( $rows as $row ) {
			\add_user_meta( $this->user_id, $key, $row );
		}

		self::assertSame( array(), ( new UserNoticeStore( $this->prefix ) )->all( $this->context ) );
		self::assertSame( $rows, $this->rows( $key ) );
	}

	public function test_another_users_notices_stay_apart(): void {
		$store = new UserNoticeStore( $this->prefix );
		$store->save( new Notice( 'one', 'One.' ), $this->context );

		$other = NoticeContext::current( NoticeScope::User, $this->make_user( 'acme_notices_editor', 'editor' ) );

		self::assertSame( array(), $store->all( $other ) );
	}

	public function test_without_a_database_connection_the_store_cannot_be_built(): void {
		$wpdb            = $GLOBALS['wpdb'];
		$GLOBALS['wpdb'] = null;

		try {
			$this->expectException( LogicException::class );
			new UserNoticeStore( $this->prefix );
		} finally {
			$GLOBALS['wpdb'] = $wpdb;
		}
	}

	#[Group( 'multisite' )]
	public function test_a_user_notice_on_another_site_lives_under_that_sites_key_and_shows_only_there(): void {
		global $wpdb;
		$site_id = $this->make_site( 'acme-notices' );
		$store   = new UserNoticeStore( $this->prefix );
		\switch_to_blog( $site_id );
		$on_site = NoticeContext::current( NoticeScope::User, $this->user_id );
		$store->save( new Notice( 'one', 'One.' ), $on_site );
		\restore_current_blog();

		self::assertSame( array( 'one' ), \array_column( $this->rows( $wpdb->get_blog_prefix( $site_id ) . "{$this->prefix}_notices" ), 'id' ) );
		self::assertSame( array(), $store->all( $this->context ) );
		self::assertSame( 'one', $store->find( 'one', $on_site )?->notice->id );
	}

	protected function rows( string $key ): array {
		return \get_user_meta( $this->user_id, $key, false );
	}
}
