<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Core\Caching;

use DeepWebSolutions\Framework\Core\Caching\CacheEntries;
use DeepWebSolutions\Framework\Core\Caching\Exceptions\InvalidCacheKeyException;
use DeepWebSolutions\Framework\Core\Caching\ObjectCache;
use DeepWebSolutions\Framework\Core\Caching\TransientCache;
use DeepWebSolutions\Framework\Shared\Exception\InvalidArgumentException;
use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesSitesTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesStorageTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface as PsrCacheInterface;
use Psr\SimpleCache\InvalidArgumentException as PsrInvalidArgumentException;

#[CoversClass( TransientCache::class )]
#[CoversClass( ObjectCache::class )]
#[CoversClass( CacheEntries::class )]
#[CoversClass( InvalidCacheKeyException::class )]
final class CachesTest extends TestCase {
	use CreatesSitesTrait;
	use IsolatesStorageTrait;

	public static function kinds(): array {
		return array(
			'transient' => array( 'transient' ),
			'object'    => array( 'object' ),
		);
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

	public static function kinds_and_values(): array {
		return self::cross( self::values() );
	}

	#[DataProvider( 'kinds_and_values' )]
	public function test_a_stored_value_reads_back_through_another_instance_and_a_falsy_one_still_hits( string $kind, mixed $value ): void {
		$sentinel = new \stdClass();
		self::assertTrue( $this->cache( $kind )->set( 'key', $value ) );

		$cache = $this->cache( $kind );

		self::assertSame( maybe_serialize( $value ), maybe_serialize( $cache->get( 'key', $sentinel ) ) );
		self::assertTrue( $cache->has( 'key' ) );
	}

	#[DataProvider( 'kinds' )]
	public function test_an_absent_key_returns_the_default( string $kind ): void {
		$cache = $this->cache( $kind );

		self::assertNull( $cache->get( 'absent' ) );
		self::assertSame( 'Fallback.', $cache->get( 'absent', 'Fallback.' ) );
		self::assertFalse( $cache->has( 'absent' ) );
	}

	public static function invalid_key_calls(): array {
		$keys  = array( '', 'a{b', 'a}b', 'a(b', 'a)b', 'a/b', 'a\\b', 'a@b', 'a:b' );
		$calls = array(
			'get'             => static fn ( PsrCacheInterface $cache, string $key ): mixed => $cache->get( $key ),
			'set'             => static fn ( PsrCacheInterface $cache, string $key ): mixed => $cache->set( $key, 'Value.' ),
			'delete'          => static fn ( PsrCacheInterface $cache, string $key ): mixed => $cache->delete( $key ),
			'has'             => static fn ( PsrCacheInterface $cache, string $key ): mixed => $cache->has( $key ),
			'get-multiple'    => static fn ( PsrCacheInterface $cache, string $key ): mixed => $cache->getMultiple( array( 'valid', $key ) ),
			'set-multiple'    => static fn ( PsrCacheInterface $cache, string $key ): mixed => $cache->setMultiple( \array_fill_keys( array( 'valid', $key ), 'Value.' ) ),
			'delete-multiple' => static fn ( PsrCacheInterface $cache, string $key ): mixed => $cache->deleteMultiple( array( 'valid', $key ) ),
		);

		$rows = array();
		foreach ( $calls as $method => $call ) {
			foreach ( $keys as $key ) {
				$rows[ "$method-" . ( '' === $key ? 'empty' : \bin2hex( $key[1] ) ) ] = array( $call, $key );
			}
		}

		return self::cross( $rows );
	}

	#[DataProvider( 'invalid_key_calls' )]
	public function test_an_invalid_key_throws_before_anything_is_read_or_written( string $kind, \Closure $call, string $key ): void {
		$cache    = $this->cache( $kind );
		$activity = $this->activity( $kind );

		try {
			$call( $cache, $key );
			self::fail( 'The key was accepted.' );
		} catch ( InvalidCacheKeyException $exception ) {
			self::assertInstanceOf( PsrInvalidArgumentException::class, $exception );
			self::assertInstanceOf( InvalidArgumentException::class, $exception );
		}

		self::assertSame( $activity, $this->activity( $kind ) );
		self::assertSame( array(), $this->footprint( $kind ) );
	}

	public static function non_string_keys(): array {
		return self::cross(
			array(
				'float-key-from-a-generator' => array( static fn ( PsrCacheInterface $cache ): mixed => $cache->setMultiple( ( static fn (): \Generator => yield 1.5 => 'Value.' )() ) ),
				'boolean-key'                => array( static fn ( PsrCacheInterface $cache ): mixed => $cache->getMultiple( array( true ) ) ),
				'null-key'                   => array( static fn ( PsrCacheInterface $cache ): mixed => $cache->deleteMultiple( array( null ) ) ),
			)
		);
	}

	#[DataProvider( 'non_string_keys' )]
	public function test_a_key_that_is_neither_a_string_nor_an_integer_throws( string $kind, \Closure $call ): void {
		$this->expectException( InvalidCacheKeyException::class );

		$call( $this->cache( $kind ) );
	}

	public static function valid_keys(): array {
		return array(
			'psr-minimum'  => array( \str_pad( 'AbC19_.', 64, 'z' ) ),
			'long'         => array( \str_repeat( 'k', 300 ) ),
			'unicode'      => array( 'café ünïcødé 🙂' ),
			'punctuation'  => array( 'a-b c+d#e%f' ),
			'numeric'      => array( '123' ),
			'whitespace'   => array( '   ' ),
			'last-changed' => array( 'last_changed' ),
		);
	}

	public static function kinds_and_valid_keys(): array {
		return self::cross( self::valid_keys() );
	}

	#[DataProvider( 'kinds_and_valid_keys' )]
	public function test_any_key_psr_16_allows_round_trips( string $kind, string $key ): void {
		self::assertTrue( $this->cache( $kind )->set( $key, 'Value.' ) );

		self::assertSame( 'Value.', $this->cache( $kind )->get( $key ) );
	}

	#[DataProvider( 'kinds' )]
	public function test_keys_that_differ_only_in_letter_case_or_accents_hold_separate_entries( string $kind ): void {
		$cache = $this->cache( $kind );
		$cache->set( 'Key', 'Upper.' );
		$cache->set( 'key', 'Lower.' );
		$cache->set( 'cafe', 'Plain.' );
		$cache->set( 'café', 'Accented.' );

		self::assertSame( array( 'Upper.', 'Lower.', 'Plain.', 'Accented.' ), \array_values( $this->cache( $kind )->getMultiple( array( 'Key', 'key', 'cafe', 'café' ) ) ) );
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
			'core-salted'     => array(
				static fn ( string $generation ): mixed => array(
					'data' => 'Value.',
					'salt' => $generation,
				),
			),
		);
	}

	public static function kinds_and_foreign_values(): array {
		return self::cross( self::foreign_values() );
	}

	#[DataProvider( 'kinds_and_foreign_values' )]
	public function test_a_value_stored_without_the_envelope_reads_as_a_miss( string $kind, \Closure $stored ): void {
		$cache = $this->cache( $kind );
		$cache->set( 'other', 'Value.' );
		$this->plant( $kind, 'key', $stored( $this->generation( $kind ) ) );

		self::assertSame( 'Default.', $cache->get( 'key', 'Default.' ) );
		self::assertFalse( $cache->has( 'key' ) );
		self::assertSame( array( 'key' => 'Default.' ), $cache->getMultiple( array( 'key' ), 'Default.' ) );
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

	public static function kinds_and_expired_lifetimes(): array {
		return self::cross( self::expired_lifetimes() );
	}

	#[DataProvider( 'kinds_and_expired_lifetimes' )]
	public function test_a_lifetime_of_zero_or_less_deletes_the_entry( string $kind, int|\DateInterval $ttl ): void {
		$cache = $this->cache( $kind );
		$cache->setMultiple( \array_fill_keys( array( 'key', 'other' ), 'Value.' ) );

		self::assertTrue( $cache->set( 'key', 'Newer.', $ttl ) );
		self::assertTrue( $cache->setMultiple( array( 'other' => 'Newer.' ), $ttl ) );
		self::assertTrue( $cache->set( 'absent', 'Value.', $ttl ) );
		self::assertTrue( $cache->setMultiple( array( 'absent' => 'Value.' ), $ttl ) );
		self::assertSame( array(), $this->entries( $kind ) );
		self::assertFalse( $cache->has( 'key' ) );
		self::assertFalse( $cache->has( 'other' ) );
	}

	#[DataProvider( 'kinds' )]
	public function test_setting_the_value_the_store_already_holds_succeeds( string $kind ): void {
		$cache = $this->cache( $kind );
		$cache->set( 'key', array( 'a' => 1 ) );

		self::assertTrue( $cache->set( 'key', array( 'a' => 1 ) ) );
		self::assertTrue( $cache->setMultiple( array( 'key' => array( 'a' => 1 ) ) ) );
	}

	#[DataProvider( 'kinds' )]
	public function test_deleting_succeeds_for_a_present_entry_and_for_an_absent_key( string $kind ): void {
		$cache = $this->cache( $kind );
		$cache->setMultiple( \array_fill_keys( array( 'key', 'other' ), 'Value.' ) );

		self::assertTrue( $cache->delete( 'key' ) );
		self::assertFalse( $cache->has( 'key' ) );
		self::assertTrue( $cache->delete( 'key' ) );
		self::assertTrue( $cache->delete( 'never-set' ) );
		self::assertTrue( $cache->deleteMultiple( array( 'other', 'never-set' ) ) );
		self::assertFalse( $cache->has( 'other' ) );
	}

	#[DataProvider( 'kinds' )]
	public function test_changing_a_stored_or_returned_object_never_changes_the_entry( string $kind ): void {
		$cache  = $this->cache( $kind );
		$object = (object) array( 'name' => 'Stored.' );
		$cache->set( 'key', array( $object ) );
		$cache->setMultiple( array( 'other' => array( $object ) ) );
		$object->name = 'Changed after set().';

		$read          = $cache->get( 'key' );
		$read[0]->name = 'Changed after get().';

		$many                   = $cache->getMultiple( array( 'other' ) );
		$many['other'][0]->name = 'Changed after getMultiple().';

		$expected = array( (object) array( 'name' => 'Stored.' ) );
		self::assertEquals( $expected, $cache->get( 'key' ) );
		self::assertEquals( $expected, $cache->get( 'other' ) );
	}

	#[DataProvider( 'kinds' )]
	public function test_clearing_makes_every_earlier_entry_miss_and_a_key_set_again_reuses_its_entry( string $kind ): void {
		$cache = $this->cache( $kind );
		$cache->set( 'a', 'A.' );
		$cache->set( 'b', 'B.' );
		$entries = $this->entries( $kind );

		self::assertTrue( $cache->clear() );
		self::assertSame( 'Default.', $cache->get( 'a', 'Default.' ) );
		self::assertFalse( $cache->has( 'b' ) );

		$cache->set( 'a', 'Again.' );
		self::assertSame( 'Again.', $cache->get( 'a' ) );
		self::assertSame( 'Default.', $cache->get( 'b', 'Default.' ) );
		self::assertSame( $entries, $this->entries( $kind ) );
	}

	#[DataProvider( 'kinds' )]
	public function test_clearing_a_cache_never_written_succeeds_and_later_writes_hit( string $kind ): void {
		$cache = $this->cache( $kind );

		self::assertTrue( $cache->clear() );
		self::assertTrue( $cache->clear() );
		self::assertFalse( $cache->has( 'key' ) );
		self::assertTrue( $cache->set( 'key', 'Value.' ) );
		self::assertSame( 'Value.', $cache->get( 'key' ) );
	}

	#[DataProvider( 'kinds' )]
	public function test_reads_of_a_cache_never_written_miss_without_a_write( string $kind ): void {
		$cache = $this->cache( $kind );

		self::assertSame( 'Default.', $cache->get( 'key', 'Default.' ) );
		self::assertFalse( $cache->has( 'key' ) );
		self::assertSame( array( 'key' => 'Default.' ), $cache->getMultiple( array( 'key' ), 'Default.' ) );
		self::assertSame( array(), $this->footprint( $kind ) );
	}

	#[DataProvider( 'kinds' )]
	public function test_a_generation_is_started_only_by_a_first_write_or_a_clear( string $kind ): void {
		$cache = $this->cache( $kind );

		$cache->get( 'key' );
		$cache->has( 'key' );
		$cache->getMultiple( array( 'key' ) );
		self::assertNull( $this->generation( $kind ) );

		self::assertTrue( $cache->set( 'key', 'Value.' ) );
		$first = $this->generation( $kind );
		self::assertNotNull( $first );
		self::assertTrue( $cache->setMultiple( array( 'other' => 'Value.' ) ) );
		self::assertSame( $first, $this->generation( $kind ) );
		self::assertSame( 'Value.', $cache->get( 'key' ) );

		self::assertTrue( $cache->clear() );
		self::assertNotSame( $first, $this->generation( $kind ) );
		self::assertFalse( $cache->has( 'key' ) );
	}

	#[DataProvider( 'kinds' )]
	public function test_the_multiple_methods_round_trip_from_arrays_and_generators( string $kind ): void {
		$cache = $this->cache( $kind );

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

	#[DataProvider( 'kinds' )]
	public function test_the_multiple_methods_accept_integer_keys_as_their_strings( string $kind ): void {
		$cache = $this->cache( $kind );

		self::assertTrue( $cache->setMultiple( array( '7' => 'Seven.' ) ) );

		self::assertSame( 'Seven.', $cache->get( '7' ) );
		self::assertSame( array( 7 => 'Seven.' ), $cache->getMultiple( array( 7, '7' ) ) );
		self::assertTrue( $cache->deleteMultiple( array( 7 ) ) );
		self::assertFalse( $cache->has( '7' ) );
	}

	#[DataProvider( 'kinds' )]
	public function test_empty_batches_succeed_and_a_repeated_key_keeps_its_last_value( string $kind ): void {
		$cache = $this->cache( $kind );

		self::assertSame( array(), $cache->getMultiple( array() ) );
		self::assertTrue( $cache->setMultiple( array() ) );
		self::assertTrue( $cache->deleteMultiple( array() ) );

		$values = static function (): \Generator {
			yield 'key' => 'First.';
			yield 'key' => 'Last.';
		};
		self::assertTrue( $cache->setMultiple( $values() ) );
		self::assertSame( 'Last.', $cache->get( 'key' ) );
	}

	#[DataProvider( 'kinds' )]
	public function test_two_instances_of_one_cache_share_entries_and_clear_together( string $kind ): void {
		$one = $this->cache( $kind );
		$two = $this->cache( $kind );
		$one->set( 'key', 'Value.' );

		self::assertSame( 'Value.', $two->get( 'key' ) );
		self::assertTrue( $two->clear() );
		self::assertFalse( $one->has( 'key' ) );
	}

	#[DataProvider( 'kinds' )]
	public function test_two_caches_never_see_or_clear_each_others_entries( string $kind ): void {
		$one = $this->cache( $kind );
		$two = $this->cache( $kind, 'two' );
		$one->set( 'key', 'One.' );
		$two->set( 'key', 'Two.' );

		$one->clear();

		self::assertSame( 'Default.', $one->get( 'key', 'Default.' ) );
		self::assertSame( 'Two.', $two->get( 'key' ) );
	}

	#[DataProvider( 'kinds' )]
	#[Group( 'multisite' )]
	public function test_entries_and_their_generation_belong_to_the_current_site( string $kind ): void {
		$site_id = $this->make_site( 'acme-cache' );
		$cache   = $this->cache( $kind );
		$cache->set( 'key', 'Main.' );

		switch_to_blog( $site_id );
		try {
			$missed = $cache->get( 'key', 'Default.' );
			$cache->set( 'key', 'Other.' );
			$cache->clear();
			$cleared = $cache->has( 'key' );
		} finally {
			restore_current_blog();
		}

		self::assertSame( 'Default.', $missed );
		self::assertFalse( $cleared );
		self::assertSame( 'Main.', $cache->get( 'key' ) );
	}

	protected static function cross( array $rows ): array {
		$crossed = array();
		foreach ( self::kinds() as $kind => $kind_row ) {
			foreach ( $rows as $name => $row ) {
				$crossed[ "$kind-$name" ] = array( ...$kind_row, ...$row );
			}
		}

		return $crossed;
	}

	protected function cache( string $kind, string $name = 'cache' ): PsrCacheInterface {
		return 'transient' === $kind ? new TransientCache( "{$this->prefix}_{$name}_" ) : new ObjectCache( "{$this->prefix}_$name" );
	}

	protected function generation( string $kind ): ?string {
		$generation = 'transient' === $kind ? get_option( "{$this->prefix}_cache_cache_generation" ) : wp_cache_get( 'last_changed', "{$this->prefix}_cache" );

		return \is_string( $generation ) ? $generation : null;
	}

	protected function plant( string $kind, string $key, mixed $value ): void {
		if ( 'transient' === $kind ) {
			set_transient( "{$this->prefix}_cache_" . \md5( $key ), $value, HOUR_IN_SECONDS );
		} else {
			wp_cache_set( \md5( $key ), $value, "{$this->prefix}_cache" );
		}
	}

	protected function activity( string $kind ): array {
		global $wpdb, $wp_object_cache;

		return 'transient' === $kind ? array( $wpdb->num_queries ) : array( $wp_object_cache->cache_hits, $wp_object_cache->cache_misses );
	}

	// Every stored name of the cache, the generation's or the salt's included.
	protected function footprint( string $kind ): array {
		global $wpdb, $wp_object_cache;

		if ( 'object' === $kind ) {
			$names = \array_map( 'strval', \array_keys( $wp_object_cache->cache[ "{$this->prefix}_cache" ] ?? array() ) );
		} else {
			$names = $wpdb->get_col( $wpdb->prepare( 'SELECT option_name FROM %i WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s', $wpdb->options, $wpdb->esc_like( $this->prefix ) . '%', $wpdb->esc_like( "_transient_$this->prefix" ) . '%', $wpdb->esc_like( "_transient_timeout_$this->prefix" ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Inspects the stored rows.
		}
		\sort( $names );

		return $names;
	}

	protected function entries( string $kind ): array {
		return \array_values( \array_filter( $this->footprint( $kind ), static fn ( string $name ): bool => ! \str_ends_with( $name, 'cache_generation' ) && ! \str_ends_with( $name, 'last_changed' ) ) );
	}
}
