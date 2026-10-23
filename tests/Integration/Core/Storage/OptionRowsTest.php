<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Core\Storage;

use DeepWebSolutions\Framework\Core\Storage\OptionRow;
use DeepWebSolutions\Framework\Core\Storage\OptionRows;
use DeepWebSolutions\Framework\Shared\Exception\InvalidArgumentException;
use DeepWebSolutions\Framework\Shared\Exception\LogicException;
use DeepWebSolutions\Framework\Shared\Exception\RuntimeException;
use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesSitesTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\InterceptsQueriesTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesStorageTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass( OptionRows::class )]
#[CoversClass( OptionRow::class )]
final class OptionRowsTest extends TestCase {
	use CreatesSitesTrait;
	use InterceptsQueriesTrait;
	use IsolatesHooksTrait;
	use IsolatesStorageTrait;

	#[\Override]
	protected function tearDown(): void {
		while ( \is_multisite() && \ms_is_switched() ) {
			\restore_current_blog();
		}
	}

	public function test_an_upserted_row_reads_back_unchanged_as_one_row_that_is_not_autoloaded(): void {
		$rows = new OptionRows();

		$written = $rows->upsert( "{$this->prefix}_one", array( 'message' => "Can't \\ \"save\"." ) );

		self::assertEquals( $written, $rows->get( "{$this->prefix}_one" ) );
		self::assertSame( array( 'message' => "Can't \\ \"save\"." ), $written->value );
		self::assertSame( \get_current_blog_id(), $written->site_id );
		self::assertSame( array( "{$this->prefix}_one" => array( \maybe_serialize( $written->value ), 'off' ) ), $this->stored() );
	}

	public function test_upserting_an_existing_name_replaces_its_value_and_an_unchanged_value_succeeds(): void {
		$rows = new OptionRows();
		$rows->upsert( "{$this->prefix}_one", 'One.' );

		$again = $rows->upsert( "{$this->prefix}_one", 'Again.' );
		$same  = $rows->upsert( "{$this->prefix}_one", 'Again.' );

		self::assertEquals( $again, $same );
		self::assertSame( array( "{$this->prefix}_one" => array( 'Again.', 'off' ) ), $this->stored() );
	}

	public function test_inserting_writes_only_a_name_that_is_absent(): void {
		$rows = new OptionRows();

		$first  = $rows->insert( "{$this->prefix}_lock", 1800000000 );
		$second = $rows->insert( "{$this->prefix}_lock", 1800000001 );

		self::assertNull( $second );
		self::assertEquals( $first, $rows->get( "{$this->prefix}_lock" ) );
		self::assertSame( array( "{$this->prefix}_lock" => array( '1800000000', 'off' ) ), $this->stored() );
	}

	public function test_an_empty_or_null_value_is_a_row_and_a_missing_name_is_none(): void {
		$rows = new OptionRows();
		$rows->upsert( "{$this->prefix}_empty", '' );
		$rows->upsert( "{$this->prefix}_null", null );

		self::assertSame( '', $rows->get( "{$this->prefix}_empty" )?->raw );
		self::assertSame( '', $rows->get( "{$this->prefix}_null" )?->raw );
		self::assertNull( $rows->get( "{$this->prefix}_missing" ) );
	}

	public function test_replacing_the_observed_row_writes_the_new_value(): void {
		$rows     = new OptionRows();
		$observed = $rows->upsert( "{$this->prefix}_state", 'Ready' );

		$replaced = $rows->replace( $observed, 'Done' );

		self::assertSame( 'Done', $replaced?->value );
		self::assertEquals( $replaced, $rows->get( "{$this->prefix}_state" ) );
		self::assertSame( array( "{$this->prefix}_state" => array( 'Done', 'off' ) ), $this->stored() );
	}

	public function test_deleting_the_observed_row_deletes_it_once(): void {
		$rows     = new OptionRows();
		$observed = $rows->upsert( "{$this->prefix}_state", 'Ready' );

		self::assertTrue( $rows->delete( $observed ) );
		self::assertFalse( $rows->delete( $observed ) );
		self::assertNull( $rows->get( "{$this->prefix}_state" ) );
	}

	public static function changes(): array {
		return array(
			'another-value'  => array( 'Other' ),
			'case-only'      => array( 'ready' ),
			'trailing-space' => array( 'Ready ' ),
			'accent-only'    => array( 'Rèady' ),
			'removed'        => array( null ),
		);
	}

	#[DataProvider( 'changes' )]
	public function test_a_row_changed_since_it_was_read_is_neither_replaced_nor_deleted( ?string $change ): void {
		$rows     = new OptionRows();
		$observed = $rows->upsert( "{$this->prefix}_state", 'Ready' );
		if ( \is_null( $change ) ) {
			$rows->remove( "{$this->prefix}_state" );
		} else {
			$rows->upsert( "{$this->prefix}_state", $change );
		}

		self::assertNull( $rows->replace( $observed, 'Done' ) );
		self::assertFalse( $rows->delete( $observed ) );
		self::assertSame( $change, $rows->get( "{$this->prefix}_state" )?->value );
	}

	public function test_replacing_with_the_observed_value_is_rejected(): void {
		$rows     = new OptionRows();
		$observed = $rows->upsert( "{$this->prefix}_state", 'Ready' );

		$this->expectException( InvalidArgumentException::class );

		$rows->replace( $observed, 'Ready' );
	}

	public function test_removing_deletes_whatever_the_row_holds_and_an_unknown_name_changes_nothing(): void {
		$rows = new OptionRows();
		$rows->upsert( "{$this->prefix}_one", 'One.' );

		$rows->remove( "{$this->prefix}_one" );
		$rows->remove( "{$this->prefix}_unknown" );

		self::assertSame( array(), $this->stored() );
	}

	public function test_listing_returns_the_rows_whose_names_start_with_the_prefix_byte_for_byte_ordered_by_name(): void {
		$rows = new OptionRows();
		$b    = $rows->upsert( "{$this->prefix}_list_b", 'B.' );
		$a    = $rows->upsert( "{$this->prefix}_list_a", 'A.' );
		$rows->upsert( "{$this->prefix}_listxc", 'Wildcard.' );
		$rows->upsert( \strtoupper( "{$this->prefix}_list_" ) . 'D', 'Case.' );

		self::assertEquals( array( $a, $b ), $rows->list( "{$this->prefix}_list_" ) );
	}

	public static function writes(): array {
		$replace = static fn ( OptionRows $rows, string $name ): mixed => $rows->replace( $rows->get( $name ) ?? throw new \LogicException( 'No row.' ), 'New.' );
		$delete  = static fn ( OptionRows $rows, string $name ): mixed => $rows->delete( $rows->get( $name ) ?? throw new \LogicException( 'No row.' ) );
		$remove  = static fn ( OptionRows $rows, string $name ): mixed => $rows->remove( $name );

		return array(
			'upsert-absent'      => array( null, static fn ( OptionRows $rows, string $name ): mixed => $rows->upsert( $name, 'New.' ), 'New.' ),
			'insert-absent'      => array( null, static fn ( OptionRows $rows, string $name ): mixed => $rows->insert( $name, 'New.' ), 'New.' ),
			'upsert-stored'      => array( false, static fn ( OptionRows $rows, string $name ): mixed => $rows->upsert( $name, 'New.' ), 'New.' ),
			'replace-stored'     => array( false, $replace, 'New.' ),
			'delete-stored'      => array( false, $delete, false ),
			'remove-stored'      => array( false, $remove, false ),
			'upsert-autoloaded'  => array( true, static fn ( OptionRows $rows, string $name ): mixed => $rows->upsert( $name, 'New.' ), 'New.' ),
			'replace-autoloaded' => array( true, $replace, 'New.' ),
			'delete-autoloaded'  => array( true, $delete, false ),
		);
	}

	#[DataProvider( 'writes' )]
	public function test_get_option_reads_every_write( ?bool $autoloaded, \Closure $write, mixed $expected ): void {
		$name = "{$this->prefix}_cached";
		if ( ! \is_null( $autoloaded ) ) {
			\add_option( $name, 'Old.', '', $autoloaded );
		}
		\get_option( $name );

		$write( new OptionRows(), $name );

		self::assertSame( $expected, \get_option( $name ) );
	}

	public function test_a_write_drops_the_cached_option_lists_that_name_the_option(): void {
		$name = "{$this->prefix}_listed";
		\add_option( "{$this->prefix}_autoloaded", 'Old.', '', true );
		\get_option( $name );
		\wp_load_alloptions();

		( new OptionRows() )->upsert( $name, 'New.' );
		$notoptions = \wp_cache_get( 'notoptions', 'options' );
		( new OptionRows() )->upsert( "{$this->prefix}_autoloaded", 'New.' );

		self::assertFalse( $notoptions );
		self::assertFalse( \wp_cache_get( 'alloptions', 'options' ) );
	}

	public function test_a_name_of_191_characters_is_kept_whole_in_multibyte_text(): void {
		$rows = new OptionRows();
		$name = \str_pad( "{$this->prefix}_", 181, 'a' ) . \str_repeat( 'é', 10 );

		$written = $rows->upsert( $name, 'Whole.' );

		self::assertSame( 191, \mb_strlen( $name ) );
		self::assertEquals( $written, $rows->get( $name ) );
	}

	public static function failing_operations(): array {
		return array(
			'get'     => array( 'SELECT option_value FROM', static fn ( OptionRows $rows, OptionRow $row ): mixed => $rows->get( $row->name ) ),
			'list'    => array( "LIKE '", static fn ( OptionRows $rows, OptionRow $row ): mixed => $rows->list( $row->name ) ),
			'upsert'  => array( 'ON DUPLICATE KEY UPDATE', static fn ( OptionRows $rows, OptionRow $row ): mixed => $rows->upsert( $row->name, 'Again.' ) ),
			'insert'  => array( 'INSERT IGNORE', static fn ( OptionRows $rows, OptionRow $row ): mixed => $rows->insert( "{$row->name}_other", 'Other.' ) ),
			'replace' => array( 'SET option_value', static fn ( OptionRows $rows, OptionRow $row ): mixed => $rows->replace( $row, 'Done.' ) ),
			'delete'  => array( 'DELETE FROM', static fn ( OptionRows $rows, OptionRow $row ): mixed => $rows->delete( $row ) ),
			'remove'  => array( 'DELETE FROM', static fn ( OptionRows $rows, OptionRow $row ): mixed => $rows->remove( $row->name ) ),
		);
	}

	#[DataProvider( 'failing_operations' )]
	public function test_a_failed_query_throws( string $needle, \Closure $operation ): void {
		$rows = new OptionRows();
		$row  = $rows->upsert( "{$this->prefix}_one", 'One.' );
		$this->fail_queries( $needle );

		$this->expectException( RuntimeException::class );

		$operation( $rows, $row );
	}

	// A filter that empties a query makes wpdb return false before it clears the previous query's result.
	public function test_a_read_whose_query_never_runs_returns_no_row_of_an_earlier_read(): void {
		$rows = new OptionRows();
		$rows->upsert( "{$this->prefix}_other", 'Other.' );
		$rows->get( "{$this->prefix}_other" );
		\add_filter( 'query', fn ( string $query ): string => \str_contains( $query, "'{$this->prefix}_absent'" ) ? '' : $query );

		self::assertNull( $rows->get( "{$this->prefix}_absent" ) );
	}

	public static function invalid_calls(): array {
		return array(
			'empty-name'       => array( static fn ( OptionRows $rows ): mixed => $rows->get( '' ) ),
			'leading-space'    => array( static fn ( OptionRows $rows ): mixed => $rows->upsert( ' acme_name', 'One.' ) ),
			'trailing-newline' => array( static fn ( OptionRows $rows ): mixed => $rows->remove( "acme_name\n" ) ),
			'name-over-191'    => array( static fn ( OptionRows $rows ): mixed => $rows->upsert( \str_repeat( 'a', 192 ), 'One.' ) ),
			'empty-prefix'     => array( static fn ( OptionRows $rows ): mixed => $rows->list( '' ) ),
			'object'           => array( static fn ( OptionRows $rows ): mixed => $rows->upsert( 'acme_name', new \stdClass() ) ),
			'nested-object'    => array( static fn ( OptionRows $rows ): mixed => $rows->insert( 'acme_name', array( 'a' => array( new \stdClass() ) ) ) ),
			'negative-site'    => array( static fn ( OptionRows $rows ): mixed => $rows->get( 'acme_name', -1 ) ),
		);
	}

	#[DataProvider( 'invalid_calls' )]
	public function test_an_invalid_name_value_or_site_is_rejected( \Closure $call ): void {
		$this->expectException( InvalidArgumentException::class );

		$call( new OptionRows() );
	}

	public function test_on_a_single_site_another_site_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );

		( new OptionRows() )->get( "{$this->prefix}_one", 2 );
	}

	public function test_an_injected_connection_reads_and_writes_the_same_rows(): void {
		$second = new \wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$second->set_prefix( $GLOBALS['wpdb']->prefix );

		$written = ( new OptionRows( $second ) )->upsert( "{$this->prefix}_one", 'One.' );

		self::assertEquals( $written, ( new OptionRows() )->get( "{$this->prefix}_one" ) );
	}

	public function test_without_a_database_connection_the_rows_cannot_be_built(): void {
		$wpdb            = $GLOBALS['wpdb'];
		$GLOBALS['wpdb'] = null;

		try {
			$this->expectException( LogicException::class );
			new OptionRows();
		} finally {
			$GLOBALS['wpdb'] = $wpdb;
		}
	}

	#[Group( 'multisite' )]
	public function test_another_sites_rows_live_in_its_table_and_its_cache_reads_every_write(): void {
		$site_id = $this->make_site( 'acme-storage' );
		$rows    = new OptionRows();
		\switch_to_blog( $site_id );
		\get_option( "{$this->prefix}_one" );
		\restore_current_blog();

		$row = $rows->upsert( "{$this->prefix}_one", 'There.', $site_id );
		\switch_to_blog( $site_id );
		$seen = \get_option( "{$this->prefix}_one" );
		\restore_current_blog();

		self::assertSame( 'There.', $seen );
		self::assertSame( $site_id, $row->site_id );
		self::assertNull( $rows->get( "{$this->prefix}_one" ) );
		self::assertEquals( array( $row ), $rows->list( "{$this->prefix}_", $site_id ) );
		self::assertTrue( $rows->delete( $row ) );
		self::assertNull( $rows->get( "{$this->prefix}_one", $site_id ) );
		self::assertSame( \get_main_site_id(), \get_current_blog_id() );
	}

	protected function stored(): array {
		global $wpdb;

		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT option_name, option_value, autoload FROM %i WHERE option_name LIKE %s ORDER BY option_name', $wpdb->options, $wpdb->esc_like( $this->prefix ) . '%' ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Inspects the stored rows.

		return \array_combine( \array_column( $rows, 'option_name' ), \array_map( static fn ( array $row ): array => array( $row['option_value'], $row['autoload'] ), $rows ) );
	}
}
