<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Core\Caching;

use DeepWebSolutions\Framework\Core\Caching\Exceptions\InvalidCacheKeyException;
use DeepWebSolutions\Framework\Core\Caching\TransientCache;
use DeepWebSolutions\Framework\Shared\Exception\InvalidArgumentException;
use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesSitesTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\InterceptsQueriesTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesStorageTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\InvalidArgumentException as PsrInvalidArgumentException;

#[CoversClass( TransientCache::class )]
#[CoversClass( InvalidCacheKeyException::class )]
final class TransientCacheTest extends TestCase {
	use CreatesSitesTrait;
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

	public static function values(): array {
		return array(
			'string'       => array( 'Hello.' ),
			'integer'      => array( 42 ),
			'float'        => array( 0.1 + 0.2 ),
			'true'         => array( true ),
			'false'        => array( false ),
			'null'         => array( null ),
			'zero'         => array( 0 ),
			'empty-string' => array( '' ),
			'empty-array'  => array( array() ),
			'nested-array' => array(
				array(
					'a' => array( 'b' => null ),
					2   => array( 1, 'two' ),
				),
			),
			'object'       => array( (object) array( 'name' => 'Acme' ) ),
		);
	}

	#[DataProvider( 'values' )]
	public function test_a_stored_value_reads_back_from_storage_and_a_falsy_one_still_hits( mixed $value ): void {
		$sentinel = new \stdClass();
		self::assertTrue( $this->cache()->set( 'key', $value ) );

		\wp_cache_flush();
		$cache = $this->cache();

		self::assertSame( \maybe_serialize( $value ), \maybe_serialize( $cache->get( 'key', $sentinel ) ) );
		self::assertTrue( $cache->has( 'key' ) );
	}

	public function test_an_absent_key_returns_the_default(): void {
		$cache = $this->cache();

		self::assertNull( $cache->get( 'absent' ) );
		self::assertSame( 'Fallback.', $cache->get( 'absent', 'Fallback.' ) );
		self::assertFalse( $cache->has( 'absent' ) );
	}

	public static function invalid_key_calls(): array {
		$keys  = array( '', 'a{b', 'a}b', 'a(b', 'a)b', 'a/b', 'a\\b', 'a@b', 'a:b' );
		$calls = array(
			'get'             => static fn ( TransientCache $cache, string $key ): mixed => $cache->get( $key ),
			'set'             => static fn ( TransientCache $cache, string $key ): mixed => $cache->set( $key, 'Value.' ),
			'delete'          => static fn ( TransientCache $cache, string $key ): mixed => $cache->delete( $key ),
			'has'             => static fn ( TransientCache $cache, string $key ): mixed => $cache->has( $key ),
			'get-multiple'    => static fn ( TransientCache $cache, string $key ): mixed => $cache->getMultiple( array( 'valid', $key ) ),
			'set-multiple'    => static fn ( TransientCache $cache, string $key ): mixed => $cache->setMultiple( \array_fill_keys( array( 'valid', $key ), 'Value.' ) ),
			'delete-multiple' => static fn ( TransientCache $cache, string $key ): mixed => $cache->deleteMultiple( array( 'valid', $key ) ),
		);

		$rows = array();
		foreach ( $calls as $method => $call ) {
			foreach ( $keys as $key ) {
				$rows[ "$method-" . ( '' === $key ? 'empty' : \bin2hex( $key[1] ) ) ] = array( $call, $key );
			}
		}

		return $rows;
	}

	#[DataProvider( 'invalid_key_calls' )]
	public function test_an_invalid_key_throws_before_anything_is_read_or_written( \Closure $call, string $key ): void {
		global $wpdb;

		$cache   = $this->cache();
		$queries = $wpdb->num_queries;

		try {
			$call( $cache, $key );
			self::fail( 'The key was accepted.' );
		} catch ( InvalidCacheKeyException $exception ) {
			self::assertInstanceOf( PsrInvalidArgumentException::class, $exception );
			self::assertInstanceOf( InvalidArgumentException::class, $exception );
		}

		self::assertSame( $queries, $wpdb->num_queries );
		self::assertSame( array(), $this->stored() );
	}

	public static function non_string_keys(): array {
		return array(
			'float-key-from-a-generator' => array( static fn ( TransientCache $cache ): mixed => $cache->setMultiple( ( static fn (): \Generator => yield 1.5 => 'Value.' )() ) ),
			'boolean-key'                => array( static fn ( TransientCache $cache ): mixed => $cache->getMultiple( array( true ) ) ),
			'null-key'                   => array( static fn ( TransientCache $cache ): mixed => $cache->deleteMultiple( array( null ) ) ),
		);
	}

	#[DataProvider( 'non_string_keys' )]
	public function test_a_key_that_is_neither_a_string_nor_an_integer_throws( \Closure $call ): void {
		$this->expectException( InvalidCacheKeyException::class );

		$call( $this->cache() );
	}

	public static function valid_keys(): array {
		return array(
			'psr-minimum' => array( \str_pad( 'AbC19_.', 64, 'z' ) ),
			'long'        => array( \str_repeat( 'k', 300 ) ),
			'unicode'     => array( 'café ünïcødé 🙂' ),
			'punctuation' => array( 'a-b c+d#e%f' ),
			'numeric'     => array( '123' ),
		);
	}

	#[DataProvider( 'valid_keys' )]
	public function test_any_key_psr_16_allows_round_trips( string $key ): void {
		self::assertTrue( $this->cache()->set( $key, 'Value.' ) );
		\wp_cache_flush();

		self::assertSame( 'Value.', $this->cache()->get( $key ) );
	}

	public function test_keys_that_differ_only_in_letter_case_or_accents_hold_separate_entries(): void {
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

	public static function foreign_values(): array {
		return array(
			'bare-string'     => array( static fn ( string $generation ): mixed => 'Value.' ),
			'list'            => array( static fn ( string $generation ): mixed => array( 'Value.', $generation ) ),
			'value-only'      => array( static fn ( string $generation ): mixed => array( 'value' => 'Value.' ) ),
			'generation-only' => array( static fn ( string $generation ): mixed => array( 'generation' => $generation ) ),
			'no-value'        => array(
				static fn ( string $generation ): mixed => array(
					'data'       => 'Value.',
					'generation' => $generation,
				),
			),
			'extra-key'       => array(
				static fn ( string $generation ): mixed => array(
					'value'      => 'Value.',
					'generation' => $generation,
					'extra'      => true,
				),
			),
		);
	}

	#[DataProvider( 'foreign_values' )]
	public function test_a_value_stored_without_the_envelope_reads_as_a_miss( \Closure $stored ): void {
		$cache = $this->cache();
		$cache->set( 'other', 'Value.' );
		\set_transient( "{$this->prefix}_" . \md5( 'key' ), $stored( \get_option( "{$this->prefix}_cache_generation" ) ), \HOUR_IN_SECONDS );

		self::assertSame( 'Default.', $cache->get( 'key', 'Default.' ) );
		self::assertFalse( $cache->has( 'key' ) );
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

	public static function expired_lifetimes(): array {
		$inverted         = new \DateInterval( 'PT1M' );
		$inverted->invert = 1;

		return array(
			'zero'              => array( 0 ),
			'negative'          => array( -60 ),
			'inverted-interval' => array( $inverted ),
		);
	}

	#[DataProvider( 'expired_lifetimes' )]
	public function test_a_lifetime_of_zero_or_less_deletes_the_entry( int|\DateInterval $ttl ): void {
		$cache = $this->cache();
		$cache->set( 'key', 'Value.' );

		self::assertTrue( $cache->set( 'key', 'Newer.', $ttl ) );
		self::assertTrue( $cache->set( 'absent', 'Value.', $ttl ) );
		self::assertSame( array( "{$this->prefix}_cache_generation" => 'on' ), $this->stored() );
		self::assertFalse( $cache->has( 'key' ) );
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

	public function test_setting_the_value_the_store_already_holds_succeeds(): void {
		$cache = $this->cache();
		$cache->set( 'key', array( 'a' => 1 ) );

		self::assertTrue( $cache->set( 'key', array( 'a' => 1 ) ) );
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

	public function test_deleting_succeeds_for_a_present_entry_and_for_an_absent_key(): void {
		$cache = $this->cache();
		$cache->set( 'key', 'Value.' );

		self::assertTrue( $cache->delete( 'key' ) );
		self::assertFalse( $cache->has( 'key' ) );
		self::assertTrue( $cache->delete( 'key' ) );
		self::assertTrue( $cache->delete( 'never-set' ) );
	}

	public function test_a_failed_delete_returns_false(): void {
		global $wpdb;

		$cache = $this->cache();
		$cache->set( 'key', 'Value.' );
		$this->fail_queries( "DELETE FROM `$wpdb->options`" );

		self::assertFalse( $cache->delete( 'key' ) );
	}

	// A timeout row left behind would expire the next entry stored under the name, because set_transient() keeps an existing timeout.
	public function test_a_delete_that_leaves_the_timeout_row_returns_false(): void {
		$cache = $this->cache();
		$cache->set( 'key', 'Value.' );
		$this->fail_queries( "_transient_timeout_{$this->prefix}_" . \md5( 'key' ) );

		self::assertFalse( $cache->delete( 'key' ) );
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

	public static function backends(): array {
		return array(
			'options-table' => array( false ),
			'object-cache'  => array( true ),
		);
	}

	#[DataProvider( 'backends' )]
	public function test_changing_a_stored_or_returned_object_never_changes_the_entry( bool $object_cache ): void {
		$previous = \wp_using_ext_object_cache( $object_cache );

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

	#[DataProvider( 'backends' )]
	public function test_clearing_makes_every_earlier_entry_miss_and_a_key_set_again_reuses_its_entry( bool $object_cache ): void {
		$previous = \wp_using_ext_object_cache( $object_cache );

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

	public function test_the_multiple_methods_round_trip_from_arrays_and_generators(): void {
		$cache = $this->cache();

		$values = static function (): \Generator {
			yield 'a' => 'A.';
			yield 'b' => false;
		};

		self::assertTrue( $cache->setMultiple( $values() ) );
		self::assertSame(
			array(
				'a' => 'A.',
				'b' => false,
				'c' => 'Default.',
			),
			$cache->getMultiple( array( 'a', 'b', 'c' ), 'Default.' )
		);
		self::assertSame( array( 'a' => 'A.' ), $cache->getMultiple( ( static fn (): \Generator => yield 'a' )() ) );
		self::assertTrue( $cache->deleteMultiple( ( static fn (): \Generator => yield from array( 'a', 'never-set' ) )() ) );
		self::assertSame(
			array(
				'a' => null,
				'b' => false,
			),
			$cache->getMultiple( array( 'a', 'b' ) )
		);
	}

	public function test_the_multiple_methods_accept_integer_keys_as_their_strings(): void {
		$cache = $this->cache();

		self::assertTrue( $cache->setMultiple( array( '7' => 'Seven.' ) ) );

		self::assertSame( 'Seven.', $cache->get( '7' ) );
		self::assertSame( array( 7 => 'Seven.' ), $cache->getMultiple( array( 7 ) ) );
		self::assertTrue( $cache->deleteMultiple( array( 7 ) ) );
		self::assertFalse( $cache->has( '7' ) );
	}

	public function test_reading_several_keys_from_the_options_table_runs_one_query(): void {
		global $wpdb;

		$cache = $this->cache();
		$cache->set( 'warm', 'Value.' );
		$queries = $wpdb->num_queries;

		$cache->getMultiple( array( 'a', 'b', 'c', 'warm' ) );

		self::assertSame( $queries + 1, $wpdb->num_queries );
	}

	public function test_a_failed_write_or_delete_among_several_returns_false(): void {
		$cache = $this->cache();
		$cache->setMultiple( \array_fill_keys( array( 'a', 'b' ), 'Prior.' ) );
		$this->fail_queries( "_transient_{$this->prefix}_" . \md5( 'a' ) );

		self::assertFalse( $cache->setMultiple( \array_fill_keys( array( 'a', 'b' ), 'Newer.' ) ) );
		self::assertSame( 'Newer.', $cache->get( 'b' ) );
		self::assertFalse( $cache->deleteMultiple( array( 'a', 'b' ) ) );
		self::assertFalse( $cache->has( 'b' ) );
	}

	public function test_two_prefixes_never_see_or_clear_each_others_entries(): void {
		$one = $this->cache();
		$two = new TransientCache( "{$this->prefix}_two_" );
		$one->set( 'key', 'One.' );
		$two->set( 'key', 'Two.' );

		$one->clear();

		self::assertSame( 'Default.', $one->get( 'key', 'Default.' ) );
		self::assertSame( 'Two.', $two->get( 'key' ) );
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

	public function test_a_generation_is_started_only_by_a_first_write_or_a_clear(): void {
		$starts = 0;
		\add_filter(
			"pre_update_option_{$this->prefix}_cache_generation",
			static function ( mixed $value ) use ( &$starts ): mixed {
				++$starts;

				return $value;
			}
		);
		$cache = $this->cache();

		$cache->get( 'key' );
		$cache->has( 'key' );
		$cache->getMultiple( array( 'key' ) );
		self::assertSame( 0, $starts );

		self::assertTrue( $cache->set( 'key', 'Value.' ) );
		self::assertTrue( $cache->set( 'other', 'Value.' ) );
		self::assertSame( 1, $starts );
		self::assertSame( 'Value.', $cache->get( 'key' ) );

		self::assertTrue( $cache->clear() );
		self::assertSame( 2, $starts );
		self::assertFalse( $cache->has( 'key' ) );
	}

	// Returning the stored value from this filter makes update_option() report a failed write, as a refused database write would.
	public function test_a_generation_that_cannot_be_stored_fails_writes_and_clears(): void {
		\add_filter( "pre_update_option_{$this->prefix}_cache_generation", static fn ( mixed $value, mixed $old_value ): mixed => $old_value, 10, 2 );
		$cache = $this->cache();

		self::assertFalse( $cache->set( 'key', 'Value.' ) );
		self::assertFalse( $cache->clear() );
		self::assertSame( array(), $this->stored() );
	}

	#[Group( 'multisite' )]
	public function test_entries_and_their_generation_belong_to_the_current_site(): void {
		$site_id = $this->make_site( 'acme-cache' );
		$cache   = $this->cache();
		$cache->set( 'key', 'Main.' );

		\switch_to_blog( $site_id );
		try {
			$missed = $cache->get( 'key', 'Default.' );
			$cache->set( 'key', 'Other.' );
			$cache->clear();
			$cleared = $cache->has( 'key' );
		} finally {
			\restore_current_blog();
		}

		self::assertSame( 'Default.', $missed );
		self::assertFalse( $cleared );
		self::assertSame( 'Main.', $cache->get( 'key' ) );
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
