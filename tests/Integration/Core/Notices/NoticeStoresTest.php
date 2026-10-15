<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Core\Notices;

use DeepWebSolutions\Framework\Notices\Internal\NoticeRecords;
use DeepWebSolutions\Framework\Notices\Internal\OptionNoticeStore;
use DeepWebSolutions\Framework\Notices\Internal\RequestNoticeStore;
use DeepWebSolutions\Framework\Notices\Internal\UserNoticeStore;
use DeepWebSolutions\Framework\Notices\Notice;
use DeepWebSolutions\Framework\Notices\NoticeContext;
use DeepWebSolutions\Framework\Notices\NoticeScope;
use DeepWebSolutions\Framework\Notices\NoticeStoreInterface;
use DeepWebSolutions\Framework\Notices\StoredNotice;
use DeepWebSolutions\Framework\Shared\Exception\InvalidArgumentException;
use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\InterceptsQueriesTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesStorageTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( RequestNoticeStore::class )]
#[CoversClass( UserNoticeStore::class )]
#[CoversClass( OptionNoticeStore::class )]
#[CoversClass( NoticeRecords::class )]
final class NoticeStoresTest extends TestCase {
	use CreatesUsersTrait;
	use InterceptsQueriesTrait;
	use IsolatesHooksTrait;
	use IsolatesStorageTrait;

	protected int $user_id;

	#[\Override]
	protected function setUp(): void {
		$this->user_id = $this->make_user( 'acme_notices_admin', 'administrator' );
	}

	public static function kinds(): array {
		return array(
			'request' => array( 'request' ),
			'user'    => array( 'user' ),
			'site'    => array( 'site' ),
			'network' => array( 'network' ),
		);
	}

	public static function persistent_kinds(): array {
		return \array_diff_key( self::kinds(), array( 'request' => true ) );
	}

	#[DataProvider( 'kinds' )]
	public function test_a_saved_notice_is_found_and_listed( string $kind ): void {
		$store   = $this->make_store( $kind );
		$context = $this->context( $kind );
		$notice  = $this->notice( $kind, 'saved', message: "Can't \\ \"save\"." );

		$record = $store->save( $notice, $context );

		self::assertEquals( $notice, $record->notice );
		self::assertEquals( $context, $record->context );
		self::assertEquals( $record, $this->reader( $kind, $store )->find( 'saved', $context ) );
		self::assertEquals( array( $record ), $this->reader( $kind, $store )->all( $context ) );
		self::assertNull( $this->reader( $kind, $store )->find( 'other', $context ) );
	}

	#[DataProvider( 'kinds' )]
	public function test_notices_are_listed_oldest_first_and_a_replacement_moves_last_as_one_record( string $kind ): void {
		$store   = $this->make_store( $kind );
		$context = $this->context( $kind );
		$first   = $store->save( $this->notice( $kind, 'b' ), $context );
		$store->save( $this->notice( $kind, 'c' ), $context );
		$store->save( $this->notice( $kind, 'a' ), $context );
		self::assertSame( array( 'b', 'c', 'a' ), self::ids( $this->reader( $kind, $store )->all( $context ) ) );

		$replacement = $store->save( $this->notice( $kind, 'b', message: 'Again.' ), $context );
		$listed      = $this->reader( $kind, $store )->all( $context );

		self::assertSame( array( 'c', 'a', 'b' ), self::ids( $listed ) );
		self::assertEquals( $replacement, $listed[2] );
		self::assertNotSame( $first->generation, $replacement->generation );
		self::assertGreaterThan( $first->created_at, $replacement->created_at );
	}

	#[DataProvider( 'kinds' )]
	public function test_consuming_the_observed_record_deletes_it_once( string $kind ): void {
		$store   = $this->make_store( $kind );
		$context = $this->context( $kind );
		$record  = $store->save( $this->notice( $kind, 'once' ), $context );

		self::assertTrue( $this->reader( $kind, $store )->consume( $record ) );
		self::assertFalse( $this->reader( $kind, $store )->consume( $record ) );
		self::assertSame( array(), $this->reader( $kind, $store )->all( $context ) );
	}

	#[DataProvider( 'kinds' )]
	public function test_a_replacement_saved_after_a_read_survives_consuming_that_read( string $kind ): void {
		$store   = $this->make_store( $kind );
		$context = $this->context( $kind );
		$store->save( $this->notice( $kind, 'once' ), $context );
		$observed = $this->reader( $kind, $store )->find( 'once', $context );

		$replacement = $store->save( $this->notice( $kind, 'once', message: 'Again.' ), $context );

		self::assertInstanceOf( StoredNotice::class, $observed );
		self::assertFalse( $this->reader( $kind, $store )->consume( $observed ) );
		self::assertEquals( $replacement, $this->reader( $kind, $store )->find( 'once', $context ) );
	}

	#[DataProvider( 'kinds' )]
	public function test_removing_a_notice_deletes_it_and_an_unknown_id_changes_nothing( string $kind ): void {
		$store   = $this->make_store( $kind );
		$context = $this->context( $kind );
		$store->save( $this->notice( $kind, 'gone' ), $context );
		$store->save( $this->notice( $kind, 'kept' ), $context );

		$store->remove( 'gone', $context );
		$store->remove( 'unknown', $context );

		self::assertSame( array( 'kept' ), self::ids( $this->reader( $kind, $store )->all( $context ) ) );
	}

	#[DataProvider( 'kinds' )]
	public function test_an_expired_notice_is_left_out_and_its_record_deleted( string $kind ): void {
		$store   = $this->make_store( $kind );
		$context = $this->context( $kind );
		$expired = $store->save( $this->notice( $kind, 'old', expires_at: \time() ), $context );
		$store->save( $this->notice( $kind, 'new', expires_at: \time() + 3600 ), $context );

		self::assertNull( $this->reader( $kind, $store )->find( 'old', $context ) );
		self::assertSame( array( 'new' ), self::ids( $this->reader( $kind, $store )->all( $context ) ) );
		self::assertFalse( $store->consume( $expired ) );
	}

	#[DataProvider( 'persistent_kinds' )]
	public function test_a_renewal_saved_while_a_read_deletes_the_expired_record_survives( string $kind ): void {
		$store   = $this->make_store( $kind );
		$context = $this->context( $kind );
		$store->save( $this->notice( $kind, 'renewed', expires_at: \time() - 1 ), $context );
		$this->before_query(
			'user' === $kind ? 'WHERE umeta_id = ' : 'CAST( option_value AS BINARY )',
			fn (): StoredNotice => $this->make_store( $kind )->save( $this->notice( $kind, 'renewed', message: 'Renewed.', expires_at: \time() + 3600 ), $context )
		);

		$listed = $store->all( $context );

		self::assertSame( array(), $listed );
		self::assertSame( 'Renewed.', $this->reader( $kind, $store )->find( 'renewed', $context )?->notice->message );
	}

	#[DataProvider( 'persistent_kinds' )]
	public function test_a_replacement_saved_while_consume_deletes_survives_and_consume_reports_it( string $kind ): void {
		$store    = $this->make_store( $kind );
		$context  = $this->context( $kind );
		$observed = $store->save( $this->notice( $kind, 'once' ), $context );
		$this->before_query(
			'user' === $kind ? 'WHERE umeta_id = ' : 'CAST( option_value AS BINARY )',
			fn (): StoredNotice => $this->make_store( $kind )->save( $this->notice( $kind, 'once', message: 'Again.' ), $context )
		);

		self::assertFalse( $store->consume( $observed ) );
		self::assertSame( 'Again.', $this->reader( $kind, $store )->find( 'once', $context )?->notice->message );
	}

	#[DataProvider( 'persistent_kinds' )]
	public function test_two_stores_that_read_and_then_save_different_notices_keep_both( string $kind ): void {
		$context = $this->context( $kind );
		$one     = $this->make_store( $kind );
		$two     = $this->make_store( $kind );
		$one->all( $context );
		$two->all( $context );

		$one->save( $this->notice( $kind, 'one' ), $context );
		$two->save( $this->notice( $kind, 'two' ), $context );

		self::assertSame( array( 'one', 'two' ), self::ids( $this->make_store( $kind )->all( $context ) ) );
	}

	public static function contexts_of_other_scopes(): array {
		$cases  = array();
		$others = array(
			'request' => 'site',
			'user'    => 'site',
			'site'    => 'user',
			'network' => 'request',
		);
		foreach ( $others as $kind => $other ) {
			foreach ( array( 'save', 'find', 'all', 'consume', 'remove' ) as $method ) {
				$cases[ "$kind-$method" ] = array( $kind, $other, $method );
			}
		}

		return $cases;
	}

	#[DataProvider( 'contexts_of_other_scopes' )]
	public function test_a_context_of_a_scope_the_store_does_not_keep_is_rejected( string $kind, string $other, string $method ): void {
		$store   = $this->make_store( $kind );
		$context = $this->context( $other );

		$this->expectException( InvalidArgumentException::class );

		match ( $method ) {
			'save'    => $store->save( $this->notice( $context->scope->value, 'x' ), $context ),
			'find'    => $store->find( 'x', $context ),
			'all'     => $store->all( $context ),
			'consume' => $store->consume( new StoredNotice( $this->notice( $context->scope->value, 'x' ), $context, 1, 'gen' ) ),
			'remove'  => $store->remove( 'x', $context ),
		};
	}

	#[DataProvider( 'kinds' )]
	public function test_a_notice_of_another_scope_is_rejected( string $kind ): void {
		$notice = $this->notice( 'site' === $kind ? 'network' : 'site', 'x' );

		$this->expectException( InvalidArgumentException::class );

		$this->make_store( $kind )->save( $notice, $this->context( $kind ) );
	}

	protected function make_store( string $kind ): NoticeStoreInterface {
		return match ( $kind ) {
			'request' => new RequestNoticeStore(),
			'user'    => new UserNoticeStore( $this->prefix ),
			default   => new OptionNoticeStore( $this->prefix ),
		};
	}

	// Request notices live in the store's memory, so only another instance of a persistent store proves the record was stored.
	protected function reader( string $kind, NoticeStoreInterface $writer ): NoticeStoreInterface {
		return 'request' === $kind ? $writer : $this->make_store( $kind );
	}

	protected function context( string $kind ): NoticeContext {
		$scope = NoticeScope::from( $kind );

		return NoticeContext::current( $scope, NoticeScope::User === $scope ? $this->user_id : 0 );
	}

	protected function notice( string $kind, string $id, mixed ...$fields ): Notice {
		$defaults = array(
			'message'    => "Notice $id.",
			'scope'      => NoticeScope::from( $kind ),
			'capability' => 'manage_options',
		);

		return new Notice( $id, ...\array_merge( $defaults, $fields ) );
	}

	protected static function ids( array $records ): array {
		return \array_map( static fn ( StoredNotice $record ): string => $record->notice->id, $records );
	}
}
