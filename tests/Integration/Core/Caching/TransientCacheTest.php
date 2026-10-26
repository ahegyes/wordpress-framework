<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Core\Caching;

use DeepWebSolutions\Framework\Core\Caching\CacheEntries;
use DeepWebSolutions\Framework\Core\Caching\TransientCache;
use DeepWebSolutions\Framework\Shared\Exception\InvalidArgumentException;
use DeepWebSolutions\Framework\Tests\Integration\Support\InterceptsQueriesTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesStorageTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;

#[CoversClass( TransientCache::class )]
#[CoversClass( CacheEntries::class )]
final class TransientCacheTest extends TestCase {
	use InterceptsQueriesTrait;
	use IsolatesHooksTrait;
	use IsolatesStorageTrait;

	public static function invalid_prefixes(): array {
		return array(
			'empty'    => array( '' ),
			'leading'  => array( ' acme_' ),
			'trailing' => array( 'acme_ ' ),
			'overlong' => array( \str_repeat( 'a', 141 ) ),
		);
	}

	#[DataProvider( 'invalid_prefixes' )]
	public function test_a_prefix_the_transient_names_would_not_keep_is_rejected( string $prefix ): void {
		$this->expectException( InvalidArgumentException::class );

		new TransientCache( $prefix );
	}

	public static function invalid_generation_options(): array {
		return array(
			'empty'    => array( '' ),
			'leading'  => array( ' acme_generation' ),
			'trailing' => array( 'acme_generation ' ),
			'overlong' => array( \str_repeat( 'a', 192 ) ),
		);
	}

	#[DataProvider( 'invalid_generation_options' )]
	public function test_a_generation_option_the_options_table_would_not_keep_is_rejected( string $option ): void {
		$this->expectException( InvalidArgumentException::class );

		new TransientCache( "{$this->prefix}_", $option );
	}

	public function test_construction_reads_and_writes_nothing(): void {
		global $wpdb;

		$queries = $wpdb->num_queries;
		new TransientCache( \str_pad( "{$this->prefix}_", 140, 'p' ) );
		new TransientCache( "{$this->prefix}_", \str_pad( "{$this->prefix}_generation", 191, 'g' ) );

		self::assertSame( $queries, $wpdb->num_queries );
		self::assertSame( array(), $this->stored() );
	}

	#[DataProviderExternal( CachesTest::class, 'values' )]
	public function test_a_stored_value_reads_back_from_storage_and_a_falsy_one_still_hits( mixed $value ): void {
		$sentinel = new \stdClass();
		self::assertTrue( $this->cache()->set( 'key', $value ) );

		\wp_cache_flush();
		$cache = $this->cache();

		self::assertSame( \maybe_serialize( $value ), \maybe_serialize( $cache->get( 'key', $sentinel ) ) );
		self::assertTrue( $cache->has( 'key' ) );
	}

	#[DataProviderExternal( CachesTest::class, 'valid_keys' )]
	public function test_any_key_psr_16_allows_round_trips_through_the_options_table( string $key ): void {
		self::assertTrue( $this->cache()->set( $key, 'Value.' ) );
		\wp_cache_flush();

		self::assertSame( 'Value.', $this->cache()->get( $key ) );
	}

	public function test_keys_that_differ_only_in_letter_case_or_accents_hold_separate_rows(): void {
		$cache = $this->cache();
		$cache->set( 'Key', 'Upper.' );
		$cache->set( 'key', 'Lower.' );
		$cache->set( 'cafe', 'Plain.' );
		$cache->set( 'café', 'Accented.' );
		\wp_cache_flush();

		self::assertSame( array( 'Upper.', 'Lower.', 'Plain.', 'Accented.' ), \array_values( $this->cache()->getMultiple( array( 'Key', 'key', 'cafe', 'café' ) ) ) );
	}

	public function test_each_entry_is_a_transient_named_by_the_hash_of_its_key_with_a_timeout(): void {
		$long = \str_repeat( 'k', 300 );
		$this->cache()->set( 'key', 'Value.' );
		$this->cache()->set( $long, 'Value.' );

		$expected = array( "{$this->prefix}_cache_generation" => 'on' );
		foreach ( array( 'key', $long ) as $key ) {
			$expected[ "_transient_{$this->prefix}_" . \md5( $key ) ]         = 'off';
			$expected[ "_transient_timeout_{$this->prefix}_" . \md5( $key ) ] = 'off';
		}
		\ksort( $expected );

		self::assertSame( $expected, $this->stored() );
	}

	public function test_a_filtered_scalar_reads_as_a_miss(): void {
		\add_filter( "pre_transient_{$this->prefix}_" . \md5( 'key' ), static fn (): string => 'Filtered.' );

		self::assertSame( 'Default.', $this->cache()->get( 'key', 'Default.' ) );
	}

	public static function lifetimes(): array {
		return array(
			'null-is-a-day'   => array( null, \DAY_IN_SECONDS ),
			'seconds'         => array( 120, 120 ),
			'hours-interval'  => array( new \DateInterval( 'PT2H' ), 7200 ),
			'months-interval' => array( new \DateInterval( 'P1M' ), null ),
		);
	}

	#[DataProvider( 'lifetimes' )]
	public function test_an_entry_expires_after_its_lifetime( null|int|\DateInterval $ttl, ?int $seconds ): void {
		$before = \time();
		$this->cache()->set( 'key', 'Value.', $ttl );
		$after = \time();

		$seconds ??= ( new \DateTimeImmutable( "@$before" ) )->add( $ttl )->getTimestamp() - $before;
		self::assertGreaterThanOrEqual( $before + $seconds, $this->timeout( 'key' ) );
		self::assertLessThanOrEqual( $after + $seconds, $this->timeout( 'key' ) );
	}

	public function test_an_entry_whose_timeout_has_passed_reads_as_the_default(): void {
		global $wpdb;

		$cache = $this->cache();
		$cache->set( 'key', 'Value.', \HOUR_IN_SECONDS );
		$wpdb->update( $wpdb->options, array( 'option_value' => \time() - 1 ), array( 'option_name' => "_transient_timeout_{$this->prefix}_" . \md5( 'key' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Expires the entry without waiting.
		\wp_cache_flush();

		self::assertSame( 'Default.', $cache->get( 'key', 'Default.' ) );
		self::assertFalse( $cache->has( 'key' ) );
	}

	public function test_a_failed_write_returns_false_and_keeps_the_prior_value(): void {
		global $wpdb;

		$cache = $this->cache();
		$cache->set( 'key', 'Prior.' );
		$this->fail_queries( "UPDATE `$wpdb->options` SET" );

		self::assertFalse( $cache->set( 'key', 'Newer.' ) );
		\wp_cache_flush();
		self::assertSame( 'Prior.', $cache->get( 'key' ) );
	}

	public function test_a_value_the_options_table_refuses_returns_false(): void {
		global $wpdb;

		$cache = $this->cache();
		$cache->set( 'other', 'Value.' );
		$wpdb->suppress_errors( true );

		try {
			self::assertFalse( $cache->set( 'key', array( 'bytes' => "\xff\xfe" ) ) );
		} finally {
			$wpdb->suppress_errors( false );
		}
		self::assertFalse( $cache->has( 'key' ) );
	}

	public function test_a_failed_delete_returns_true_because_wordpress_reads_the_entry_as_absent(): void {
		global $wpdb;

		$cache = $this->cache();
		$cache->set( 'key', 'Value.' );
		$this->fail_queries( "DELETE FROM `$wpdb->options`" );

		self::assertTrue( $cache->delete( 'key' ) );
		self::assertFalse( $cache->has( 'key' ) );
	}

	public function test_a_delete_that_leaves_the_timeout_row_returns_true(): void {
		$cache = $this->cache();
		$cache->set( 'key', 'Value.' );
		$this->fail_queries( "_transient_timeout_{$this->prefix}_" . \md5( 'key' ) );

		self::assertTrue( $cache->delete( 'key' ) );
		self::assertFalse( $cache->has( 'key' ) );
	}

	public function test_behind_a_persistent_object_cache_the_same_results_hold(): void {
		$previous = \wp_using_ext_object_cache( true );

		try {
			$cache = $this->cache();
			self::assertTrue( $cache->set( 'key', false ) );
			self::assertTrue( $cache->set( 'key', false ) );
			self::assertFalse( $cache->get( 'key', 'Default.' ) );
			self::assertTrue( $cache->delete( 'key' ) );
			self::assertTrue( $cache->delete( 'key' ) );
			self::assertSame( 'Default.', $cache->get( 'key', 'Default.' ) );
			self::assertTrue( $cache->set( 'key', 'Value.', 0 ) );
			self::assertSame( array( "{$this->prefix}_cache_generation" => 'on' ), $this->stored() );
		} finally {
			// The flag is null without a drop-in, and passing null only reads it.
			$GLOBALS['_wp_using_ext_object_cache'] = $previous;
		}
	}

	public function test_behind_a_persistent_object_cache_changing_a_stored_or_returned_object_never_changes_the_entry(): void {
		$previous = \wp_using_ext_object_cache( true );

		try {
			$cache  = $this->cache();
			$object = (object) array( 'name' => 'Stored.' );
			$cache->set( 'key', array( $object ) );
			$object->name = 'Changed after set().';

			$read          = $cache->get( 'key' );
			$read[0]->name = 'Changed after get().';

			self::assertEquals( array( (object) array( 'name' => 'Stored.' ) ), $cache->get( 'key' ) );
		} finally {
			$GLOBALS['_wp_using_ext_object_cache'] = $previous;
		}
	}

	public function test_behind_a_persistent_object_cache_clearing_makes_every_earlier_entry_miss_and_a_key_set_again_reuses_its_entry(): void {
		$previous = \wp_using_ext_object_cache( true );

		try {
			$cache = $this->cache();
			$cache->set( 'a', 'A.' );
			$cache->set( 'b', 'B.' );
			$stored = $this->stored();

			self::assertTrue( $cache->clear() );
			self::assertSame( 'Default.', $cache->get( 'a', 'Default.' ) );
			self::assertFalse( $cache->has( 'b' ) );

			$cache->set( 'a', 'Again.' );
			self::assertSame( 'Again.', $cache->get( 'a' ) );
			self::assertSame( 'Default.', $cache->get( 'b', 'Default.' ) );
			self::assertSame( \array_keys( $stored ), \array_keys( $this->stored() ) );
		} finally {
			$GLOBALS['_wp_using_ext_object_cache'] = $previous;
		}
	}

	public function test_each_clear_stores_a_new_random_autoloaded_generation_even_on_a_cache_never_used(): void {
		$cache = $this->cache();

		self::assertTrue( $cache->clear() );
		$first = \get_option( "{$this->prefix}_cache_generation" );
		self::assertTrue( $cache->clear() );
		$second = \get_option( "{$this->prefix}_cache_generation" );

		self::assertMatchesRegularExpression( '/\A[0-9a-f]{16}\z/', $first );
		self::assertMatchesRegularExpression( '/\A[0-9a-f]{16}\z/', $second );
		self::assertNotSame( $first, $second );
		self::assertSame( array( "{$this->prefix}_cache_generation" => 'on' ), $this->stored() );
	}

	public static function unusable_generations(): array {
		return array(
			'empty' => array( '' ),
			'array' => array( array( 'token' ) ),
		);
	}

	#[DataProvider( 'unusable_generations' )]
	public function test_a_generation_option_holding_no_non_empty_string_reads_as_none_until_a_write_starts_one( mixed $stored ): void {
		\update_option( "{$this->prefix}_cache_generation", $stored );
		\set_transient(
			"{$this->prefix}_" . \md5( 'key' ),
			array(
				'value'      => 'Value.',
				'generation' => $stored,
			),
			\HOUR_IN_SECONDS
		);
		$cache = $this->cache();

		self::assertSame( 'Default.', $cache->get( 'key', 'Default.' ) );
		self::assertTrue( $cache->set( 'other', 'Other.' ) );
		self::assertMatchesRegularExpression( '/\A[0-9a-f]{16}\z/', \get_option( "{$this->prefix}_cache_generation" ) );
		self::assertSame( 'Other.', $cache->get( 'other' ) );
	}

	public function test_without_a_generation_every_read_misses_without_a_write(): void {
		\set_transient(
			"{$this->prefix}_" . \md5( 'key' ),
			array(
				'value'      => 'Value.',
				'generation' => null,
			),
			\HOUR_IN_SECONDS
		);
		$stored = $this->stored();
		$cache  = $this->cache();

		self::assertSame( 'Default.', $cache->get( 'key', 'Default.' ) );
		self::assertFalse( $cache->has( 'key' ) );
		self::assertSame( array( 'key' => 'Default.' ), $cache->getMultiple( array( 'key' ), 'Default.' ) );
		self::assertSame( $stored, $this->stored() );
	}

	public function test_an_entry_from_before_the_generation_was_deleted_never_comes_back(): void {
		$cache = $this->cache();
		$cache->set( 'old', 'Old.' );
		\delete_option( "{$this->prefix}_cache_generation" );

		$cache->set( 'new', 'New.' );

		self::assertSame( 'New.', $cache->get( 'new' ) );
		self::assertSame( 'Default.', $cache->get( 'old', 'Default.' ) );
	}

	public function test_reading_several_keys_from_the_options_table_runs_one_query(): void {
		global $wpdb;

		$cache = $this->cache();
		$cache->set( 'warm', 'Value.' );
		$queries = $wpdb->num_queries;

		$cache->getMultiple( array( 'a', 'b', 'c', 'warm' ) );

		self::assertSame( $queries + 1, $wpdb->num_queries );
	}

	// Every query naming the entry fails, so delete_option() fails at its lookup, before it marks the entry absent, and the entry stays readable.
	public function test_a_failed_write_or_delete_among_several_returns_false(): void {
		$cache = $this->cache();
		$cache->setMultiple( \array_fill_keys( array( 'a', 'b' ), 'Prior.' ) );
		$this->fail_queries( "_transient_{$this->prefix}_" . \md5( 'a' ) );

		self::assertFalse( $cache->setMultiple( \array_fill_keys( array( 'a', 'b' ), 'Newer.' ) ) );
		self::assertSame( 'Newer.', $cache->get( 'b' ) );
		self::assertFalse( $cache->deleteMultiple( array( 'a', 'b' ) ) );
		self::assertFalse( $cache->has( 'b' ) );
	}

	public function test_caches_that_share_a_generation_option_clear_together(): void {
		$one = new TransientCache( "{$this->prefix}_one_", "{$this->prefix}_shared_generation" );
		$two = new TransientCache( "{$this->prefix}_two_", "{$this->prefix}_shared_generation" );
		$one->set( 'key', 'One.' );
		$two->set( 'key', 'Two.' );

		self::assertTrue( $one->clear() );

		self::assertFalse( $one->has( 'key' ) );
		self::assertFalse( $two->has( 'key' ) );
		self::assertFalse( \get_option( "{$this->prefix}_one_cache_generation" ) );
	}

	// Returning the stored value from this filter makes update_option() report a failed write, as a refused database write would.
	public function test_a_generation_that_cannot_be_stored_fails_writes_and_clears(): void {
		\add_filter( "pre_update_option_{$this->prefix}_cache_generation", static fn ( mixed $value, mixed $old_value ): mixed => $old_value, 10, 2 );
		$cache = $this->cache();

		self::assertFalse( $cache->set( 'key', 'Value.' ) );
		self::assertFalse( $cache->clear() );
		self::assertSame( array(), $this->stored() );
	}

	public function test_behind_a_persistent_object_cache_a_failed_delete_returns_false(): void {
		global $wp_object_cache;

		$previous = \wp_using_ext_object_cache( true );
		$original = $wp_object_cache;

		$wp_object_cache = new class() extends \WP_Object_Cache {
			#[\Override]
			public function delete( $key, $group = 'default', $deprecated = false ) {
				return false;
			}
		};

		try {
			$cache = $this->cache();
			$cache->set( 'key', 'Value.' );

			self::assertFalse( $cache->delete( 'key' ) );
			self::assertSame( 'Value.', $cache->get( 'key' ) );
		} finally {
			$wp_object_cache                       = $original;
			$GLOBALS['_wp_using_ext_object_cache'] = $previous;
		}
	}

	protected function cache(): TransientCache {
		return new TransientCache( "{$this->prefix}_" );
	}

	protected function timeout( string $key ): int {
		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT option_value FROM %i WHERE option_name = %s', $wpdb->options, "_transient_timeout_{$this->prefix}_" . \md5( $key ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Inspects the stored timeout.
	}

	protected function stored(): array {
		global $wpdb;

		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT option_name, autoload FROM %i WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s ORDER BY option_name', $wpdb->options, $wpdb->esc_like( $this->prefix ) . '%', $wpdb->esc_like( "_transient_$this->prefix" ) . '%', $wpdb->esc_like( "_transient_timeout_$this->prefix" ) . '%' ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Inspects the stored rows.

		$stored = \array_column( $rows, 'autoload', 'option_name' );
		\ksort( $stored );

		return $stored;
	}
}
