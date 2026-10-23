<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Core\Storage;

use DeepWebSolutions\Framework\Core\Storage\MetaRow;
use DeepWebSolutions\Framework\Core\Storage\MetaRows;
use DeepWebSolutions\Framework\Shared\Exception\InvalidArgumentException;
use DeepWebSolutions\Framework\Shared\Exception\LogicException;
use DeepWebSolutions\Framework\Shared\Exception\RuntimeException;
use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesSitesTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\InterceptsQueriesTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesStorageTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass( MetaRows::class )]
#[CoversClass( MetaRow::class )]
final class MetaRowsTest extends TestCase {
	use CreatesSitesTrait;
	use CreatesUsersTrait;
	use InterceptsQueriesTrait;
	use IsolatesHooksTrait;
	use IsolatesStorageTrait;

	protected int $user_id;

	#[\Override]
	protected function setUp(): void {
		$this->user_id = $this->make_user( 'acme_storage_user', 'subscriber' );
	}

	#[\Override]
	protected function tearDown(): void {
		while ( \is_multisite() && \ms_is_switched() ) {
			\restore_current_blog();
		}
	}

	public function test_added_rows_are_listed_in_order_with_their_key_and_value_intact(): void {
		$rows = new MetaRows( 'user' );
		$key  = "{$this->prefix}_back\\slash";

		$one = $rows->add( $this->user_id, $key, array( 'message' => "Can't \\ \"save\"." ) );
		$two = $rows->add( $this->user_id, $key, 'Two \\ slashes.' );

		self::assertEquals( array( $one, $two ), $rows->list( $this->user_id, $key ) );
		self::assertSame( array( array( 'message' => "Can't \\ \"save\"." ), 'Two \\ slashes.' ), \get_user_meta( $this->user_id, $key, false ) );
		self::assertSame( $GLOBALS['wpdb']->usermeta, $one->table );
	}

	public function test_a_key_that_differs_only_in_letter_case_is_another_key(): void {
		$rows = new MetaRows( 'user' );
		$rows->add( $this->user_id, \strtoupper( "{$this->prefix}_key" ), 'Upper.' );

		self::assertSame( array(), $rows->list( $this->user_id, "{$this->prefix}_key" ) );
	}

	public function test_another_objects_rows_stay_apart(): void {
		$rows = new MetaRows( 'user' );
		$rows->add( $this->make_user( 'acme_storage_other', 'subscriber' ), "{$this->prefix}_key", 'Other.' );

		self::assertSame( array(), $rows->list( $this->user_id, "{$this->prefix}_key" ) );
	}

	public function test_a_key_of_255_characters_is_kept_whole_in_multibyte_text(): void {
		$rows = new MetaRows( 'user' );
		$key  = \str_pad( "{$this->prefix}_", 245, 'a' ) . \str_repeat( 'é', 10 );

		$row = $rows->add( $this->user_id, $key, 'Whole.' );

		self::assertSame( 255, \mb_strlen( $key ) );
		self::assertEquals( array( $row ), $rows->list( $this->user_id, $key ) );
	}

	public function test_deleting_one_of_two_identical_rows_keeps_the_other(): void {
		$rows   = new MetaRows( 'user' );
		$first  = $rows->add( $this->user_id, "{$this->prefix}_key", 'Same.' );
		$second = $rows->add( $this->user_id, "{$this->prefix}_key", 'Same.' );

		self::assertTrue( $rows->delete( $first ) );
		self::assertEquals( array( $second ), $rows->list( $this->user_id, "{$this->prefix}_key" ) );
		self::assertSame( array( 'Same.' ), \get_user_meta( $this->user_id, "{$this->prefix}_key", false ) );
	}

	public static function empty_values(): array {
		return array(
			'empty-string' => array( '' ),
			'false'        => array( false ),
			'null'         => array( null ),
		);
	}

	#[DataProvider( 'empty_values' )]
	public function test_deleting_an_empty_row_keeps_the_keys_other_rows( mixed $blank_value ): void {
		$rows  = new MetaRows( 'user' );
		$kept  = $rows->add( $this->user_id, "{$this->prefix}_key", 'Kept.' );
		$blank = $rows->add( $this->user_id, "{$this->prefix}_key", $blank_value );

		self::assertTrue( $rows->delete( $blank ) );
		self::assertSame( array( $kept->id ), \array_column( $rows->list( $this->user_id, "{$this->prefix}_key" ), 'id' ) );
	}

	public function test_deleting_a_row_that_is_gone_returns_false(): void {
		$rows = new MetaRows( 'user' );
		$row  = $rows->add( $this->user_id, "{$this->prefix}_key", 'Once.' );
		$rows->delete( $row );

		self::assertFalse( $rows->delete( $row ) );
	}

	public function test_post_meta_rows_work_as_user_meta_rows_do(): void {
		$post_id = \wp_insert_post( array( 'post_title' => 'Acme' ), true );
		self::assertIsInt( $post_id );

		try {
			$rows = new MetaRows( 'post' );
			$row  = $rows->add( $post_id, "{$this->prefix}_key", 'Post.' );

			self::assertEquals( array( $row ), $rows->list( $post_id, "{$this->prefix}_key" ) );
			self::assertTrue( $rows->delete( $row ) );
			self::assertSame( array(), \get_post_meta( $post_id, "{$this->prefix}_key", false ) );
		} finally {
			\wp_delete_post( $post_id, true );
		}
	}

	public function test_a_failed_add_throws(): void {
		\add_filter( 'add_user_metadata', '__return_false' );

		$this->expectException( RuntimeException::class );

		( new MetaRows( 'user' ) )->add( $this->user_id, "{$this->prefix}_key", 'Refused.' );
	}

	public static function failing_operations(): array {
		return array(
			'list'   => array( 'ORDER BY', static fn ( MetaRows $rows, MetaRow $row ): mixed => $rows->list( $row->object_id, $row->key ) ),
			'delete' => array( 'DELETE FROM', static fn ( MetaRows $rows, MetaRow $row ): mixed => $rows->delete( $row ) ),
		);
	}

	#[DataProvider( 'failing_operations' )]
	public function test_a_failed_query_throws( string $needle, \Closure $operation ): void {
		$rows = new MetaRows( 'user' );
		$row  = $rows->add( $this->user_id, "{$this->prefix}_key", 'One.' );
		$this->fail_queries( $needle );

		$this->expectException( RuntimeException::class );

		$operation( $rows, $row );
	}

	public static function invalid_calls(): array {
		return array(
			'unknown-type'        => array( static fn (): mixed => new MetaRows( 'acme' ) ),
			'key-over-255'        => array( static fn (): mixed => ( new MetaRows( 'user' ) )->add( 1, \str_repeat( 'a', 256 ), 'One.' ) ),
			'object'              => array( static fn (): mixed => ( new MetaRows( 'user' ) )->add( 1, 'acme_key', new \stdClass() ) ),
			'nested-object'       => array( static fn (): mixed => ( new MetaRows( 'user' ) )->add( 1, 'acme_key', array( 'a' => array( new \stdClass() ) ) ) ),
			'row-of-another-type' => array( static fn (): mixed => ( new MetaRows( 'post' ) )->delete( new MetaRow( $GLOBALS['wpdb']->usermeta, 1, 1, 'acme_key', 'One.' ) ) ),
		);
	}

	#[DataProvider( 'invalid_calls' )]
	public function test_an_unknown_type_an_object_value_or_a_row_of_another_table_is_rejected( \Closure $call ): void {
		$this->expectException( InvalidArgumentException::class );

		$call();
	}

	public function test_without_a_database_connection_the_rows_cannot_be_built(): void {
		$wpdb            = $GLOBALS['wpdb'];
		$GLOBALS['wpdb'] = null;

		try {
			$this->expectException( LogicException::class );
			new MetaRows( 'user' );
		} finally {
			$GLOBALS['wpdb'] = $wpdb;
		}
	}

	#[Group( 'multisite' )]
	public function test_a_post_meta_row_read_on_another_site_is_not_deleted_after_switching_back(): void {
		$site_id = $this->make_site( 'acme-storage' );
		\switch_to_blog( $site_id );
		$post_id = \wp_insert_post( array( 'post_title' => 'Acme' ), true );
		self::assertIsInt( $post_id );
		$row = ( new MetaRows( 'post' ) )->add( $post_id, "{$this->prefix}_key", 'There.' );
		\restore_current_blog();

		$rejected = false;
		try {
			( new MetaRows( 'post' ) )->delete( $row );
		} catch ( InvalidArgumentException ) {
			$rejected = true;
		}
		\switch_to_blog( $site_id );
		$kept = \get_post_meta( $post_id, "{$this->prefix}_key", false );
		\wp_delete_post( $post_id, true );
		\restore_current_blog();

		self::assertTrue( $rejected );
		self::assertSame( array( 'There.' ), $kept );
	}

	#[Group( 'multisite' )]
	public function test_a_user_meta_row_read_on_another_site_is_deleted_after_switching_back(): void {
		$site_id = $this->make_site( 'acme-storage' );
		\switch_to_blog( $site_id );
		$row = ( new MetaRows( 'user' ) )->add( $this->user_id, "{$this->prefix}_key", 'Everywhere.' );
		\restore_current_blog();

		self::assertTrue( ( new MetaRows( 'user' ) )->delete( $row ) );
	}
}
