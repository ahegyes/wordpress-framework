<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Core\Storage;

use DeepWebSolutions\Framework\Core\Storage\OptionRow;
use DeepWebSolutions\Framework\Core\Storage\OptionRows;
use DeepWebSolutions\Framework\Shared\Exception\InvalidArgumentException;
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
		while ( is_multisite() && ms_is_switched() ) {
			restore_current_blog();
		}
	}

	public function test_an_inserted_row_reads_back_unchanged_as_one_row_that_is_not_autoloaded(): void {
		$written = self::insert( "{$this->prefix}_one", array( 'message' => "Can't \\ \"save\"." ) );

		self::assertEquals( $written, OptionRows::get( "{$this->prefix}_one" ) );
		self::assertSame( array( 'message' => "Can't \\ \"save\"." ), $written->value );
		self::assertSame( array( "{$this->prefix}_one" => array( maybe_serialize( $written->value ), 'off' ) ), $this->stored() );
	}

	public function test_inserting_writes_only_a_name_that_is_absent(): void {
		$first  = OptionRows::insert( "{$this->prefix}_lock", 1800000000 );
		$second = OptionRows::insert( "{$this->prefix}_lock", 1800000001 );

		self::assertNull( $second );
		self::assertEquals( $first, OptionRows::get( "{$this->prefix}_lock" ) );
		self::assertSame( array( "{$this->prefix}_lock" => array( '1800000000', 'off' ) ), $this->stored() );
	}

	public function test_an_empty_or_null_value_is_a_row_and_a_missing_name_is_none(): void {
		self::insert( "{$this->prefix}_empty", '' );
		self::insert( "{$this->prefix}_null", null );

		self::assertSame( '', OptionRows::get( "{$this->prefix}_empty" )?->raw );
		self::assertSame( '', OptionRows::get( "{$this->prefix}_null" )?->raw );
		self::assertNull( OptionRows::get( "{$this->prefix}_missing" ) );
	}

	public function test_replacing_the_observed_row_writes_the_new_value(): void {
		$observed = self::insert( "{$this->prefix}_state", 'Ready' );

		$replaced = OptionRows::replace( $observed, 'Done' );

		self::assertSame( 'Done', $replaced?->value );
		self::assertEquals( $replaced, OptionRows::get( "{$this->prefix}_state" ) );
		self::assertSame( array( "{$this->prefix}_state" => array( 'Done', 'off' ) ), $this->stored() );
	}

	public function test_a_serialized_looking_string_reads_back_as_the_string_it_was(): void {
		$observed = self::insert( "{$this->prefix}_text", 'a:1:{i:0;s:1:"x";}' );

		$replaced = OptionRows::replace( $observed, 'O:8:"stdClass":0:{}' );

		self::assertSame( 'a:1:{i:0;s:1:"x";}', $observed->value );
		self::assertSame( 'O:8:"stdClass":0:{}', $replaced?->value );
		self::assertSame( 'O:8:"stdClass":0:{}', OptionRows::get( "{$this->prefix}_text" )?->value );
		self::assertSame( 'O:8:"stdClass":0:{}', get_option( "{$this->prefix}_text" ) );
	}

	public function test_deleting_the_observed_row_deletes_it_once(): void {
		$observed = self::insert( "{$this->prefix}_state", 'Ready' );

		self::assertTrue( OptionRows::delete( $observed ) );
		self::assertFalse( OptionRows::delete( $observed ) );
		self::assertNull( OptionRows::get( "{$this->prefix}_state" ) );
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
		$observed = self::insert( "{$this->prefix}_state", 'Ready' );
		if ( \is_null( $change ) ) {
			delete_option( "{$this->prefix}_state" );
		} else {
			update_option( "{$this->prefix}_state", $change );
		}

		self::assertNull( OptionRows::replace( $observed, 'Done' ) );
		self::assertFalse( OptionRows::delete( $observed ) );
		self::assertSame( $change, OptionRows::get( "{$this->prefix}_state" )?->value );
	}

	public function test_replacing_with_the_observed_value_is_rejected(): void {
		$observed = self::insert( "{$this->prefix}_state", 'Ready' );

		$this->expectException( InvalidArgumentException::class );

		OptionRows::replace( $observed, 'Ready' );
	}

	public static function writes(): array {
		$replace = static fn ( string $name ): mixed => OptionRows::replace( OptionRows::get( $name ) ?? throw new \LogicException( 'No row.' ), 'New.' );
		$delete  = static fn ( string $name ): mixed => OptionRows::delete( OptionRows::get( $name ) ?? throw new \LogicException( 'No row.' ) );

		return array(
			'insert-absent'      => array( null, static fn ( string $name ): mixed => OptionRows::insert( $name, 'New.' ), 'New.' ),
			'replace-stored'     => array( false, $replace, 'New.' ),
			'delete-stored'      => array( false, $delete, false ),
			'replace-autoloaded' => array( true, $replace, 'New.' ),
			'delete-autoloaded'  => array( true, $delete, false ),
		);
	}

	#[DataProvider( 'writes' )]
	public function test_get_option_reads_every_write( ?bool $autoloaded, \Closure $write, mixed $expected ): void {
		$name = "{$this->prefix}_cached";
		if ( ! \is_null( $autoloaded ) ) {
			add_option( $name, 'Old.', '', $autoloaded );
		}
		get_option( $name );

		$write( $name );

		self::assertSame( $expected, get_option( $name ) );
	}

	public function test_a_write_drops_the_cached_option_lists_that_name_the_option(): void {
		$name = "{$this->prefix}_listed";
		add_option( "{$this->prefix}_autoloaded", 'Old.', '', true );
		get_option( $name );
		wp_load_alloptions();

		self::insert( $name, 'New.' );
		$notoptions = wp_cache_get( 'notoptions', 'options' );
		OptionRows::replace( OptionRows::get( "{$this->prefix}_autoloaded" ) ?? throw new \LogicException( 'No row.' ), 'New.' );

		self::assertFalse( $notoptions );
		self::assertFalse( wp_cache_get( 'alloptions', 'options' ) );
	}

	public function test_a_name_of_191_characters_is_kept_whole_in_multibyte_text(): void {
		$name = \str_pad( "{$this->prefix}_", 181, 'a' ) . \str_repeat( 'é', 10 );

		$written = self::insert( $name, 'Whole.' );

		self::assertSame( 191, \mb_strlen( $name ) );
		self::assertEquals( $written, OptionRows::get( $name ) );
	}

	public static function failing_operations(): array {
		return array(
			'get'     => array( 'SELECT option_value FROM', static fn ( OptionRow $row ): mixed => OptionRows::get( $row->name ) ),
			'insert'  => array( 'INSERT IGNORE', static fn ( OptionRow $row ): mixed => OptionRows::insert( "{$row->name}_other", 'Other.' ) ),
			'replace' => array( 'SET option_value', static fn ( OptionRow $row ): mixed => OptionRows::replace( $row, 'Done.' ) ),
			'delete'  => array( 'DELETE FROM', static fn ( OptionRow $row ): mixed => OptionRows::delete( $row ) ),
		);
	}

	#[DataProvider( 'failing_operations' )]
	public function test_a_failed_query_throws( string $needle, \Closure $operation ): void {
		$row = self::insert( "{$this->prefix}_one", 'One.' );
		$this->fail_queries( $needle );

		$this->expectException( RuntimeException::class );

		$operation( $row );
	}

	// A filter that empties a query makes wpdb return false before it clears the previous query's result.
	public function test_a_read_whose_query_never_runs_returns_no_row_of_an_earlier_read(): void {
		self::insert( "{$this->prefix}_other", 'Other.' );
		OptionRows::get( "{$this->prefix}_other" );
		add_filter( 'query', fn ( string $query ): string => \str_contains( $query, "'{$this->prefix}_absent'" ) ? '' : $query );

		self::assertNull( OptionRows::get( "{$this->prefix}_absent" ) );
	}

	public static function invalid_calls(): array {
		return array(
			'empty-name'       => array( static fn (): mixed => OptionRows::get( '' ) ),
			'leading-space'    => array( static fn (): mixed => OptionRows::insert( ' acme_name', 'One.' ) ),
			'trailing-newline' => array( static fn (): mixed => OptionRows::get( "acme_name\n" ) ),
			'name-over-191'    => array( static fn (): mixed => OptionRows::insert( \str_repeat( 'a', 192 ), 'One.' ) ),
			'object'           => array( static fn (): mixed => OptionRows::insert( 'acme_name', new \stdClass() ) ),
			'nested-object'    => array( static fn (): mixed => OptionRows::insert( 'acme_name', array( 'a' => array( new \stdClass() ) ) ) ),
		);
	}

	#[DataProvider( 'invalid_calls' )]
	public function test_an_invalid_name_or_value_is_rejected( \Closure $call ): void {
		$this->expectException( InvalidArgumentException::class );

		$call();
	}

	public function test_a_value_of_the_wrong_type_is_reported_before_an_invalid_name(): void {
		$this->expectExceptionMessage( 'holds something other than scalars, null and arrays' );

		OptionRows::insert( '', new \stdClass() );
	}

	#[Group( 'multisite' )]
	public function test_inside_a_switch_rows_live_in_the_switched_sites_table_and_its_cache_reads_every_write(): void {
		$site_id = $this->make_site( 'acme-storage' );
		switch_to_blog( $site_id );
		get_option( "{$this->prefix}_one" );
		$row  = self::insert( "{$this->prefix}_one", 'There.' );
		$seen = get_option( "{$this->prefix}_one" );
		restore_current_blog();

		self::assertSame( 'There.', $seen );
		self::assertNull( OptionRows::get( "{$this->prefix}_one" ) );
		switch_to_blog( $site_id );
		self::assertTrue( OptionRows::delete( $row ) );
		self::assertFalse( get_option( "{$this->prefix}_one" ) );
	}

	protected static function insert( string $name, mixed $value ): OptionRow {
		return OptionRows::insert( $name, $value ) ?? throw new \LogicException( "Option '$name' exists." );
	}

	protected function stored(): array {
		global $wpdb;

		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT option_name, option_value, autoload FROM %i WHERE option_name LIKE %s ORDER BY option_name', $wpdb->options, $wpdb->esc_like( $this->prefix ) . '%' ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Inspects the stored rows.

		return \array_combine( \array_column( $rows, 'option_name' ), \array_map( static fn ( array $row ): array => array( $row['option_value'], $row['autoload'] ), $rows ) );
	}
}
