<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Integration\Caching;

use DeepWebSolutions\Framework\Utilities\Caching\ObjectCache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( ObjectCache::class )]
final class ObjectCacheTest extends TestCase {
	private const GROUP = 'dws_oc_test';

	protected function setUp(): void {
		parent::setUp();
		\delete_option( self::GROUP . '_object_cache_generation' );
		\wp_cache_flush();
	}

	protected function tearDown(): void {
		\delete_option( self::GROUP . '_object_cache_generation' );
		\wp_cache_flush();
		parent::tearDown();
	}

	public function test_set_then_get_round_trips_a_value(): void {
		$cache = new ObjectCache( self::GROUP );
		$cache->set( 'name', 'Ada' );

		self::assertSame( 'Ada', $cache->get( 'name' ) );
	}

	public function test_get_returns_the_default_on_a_miss(): void {
		$cache = new ObjectCache( self::GROUP );

		self::assertSame( 'fallback', $cache->get( 'absent', 'fallback' ) );
		self::assertNull( $cache->get( 'absent' ) );
	}

	#[DataProvider( 'falsey_values' )]
	public function test_get_reads_a_cached_falsey_value_as_a_hit_not_a_miss( mixed $value ): void {
		$cache = new ObjectCache( self::GROUP );
		$cache->set( 'flag', $value );

		self::assertSame( $value, $cache->get( 'flag', 'default' ) );
		self::assertSame( 'default', $cache->get( 'absent', 'default' ) );
	}

	/**
	 * @return iterable<string, array{mixed}>
	 */
	public static function falsey_values(): iterable {
		yield 'false'        => array( false );
		yield 'null'         => array( null );
		yield 'zero'         => array( 0 );
		yield 'empty string' => array( '' );
	}

	public function test_get_multiple_cannot_distinguish_a_stored_false_from_a_miss(): void {
		$cache = new ObjectCache( self::GROUP );
		$cache->set( 'stored_false', false );

		$result = $cache->get_multiple( array( 'stored_false', 'absent' ) );

		// Both read back as false: get_multiple has no per-key found flag, unlike get().
		self::assertFalse( $result['stored_false'] );
		self::assertFalse( $result['absent'] );
	}

	public function test_remember_computes_and_caches_on_a_miss(): void {
		$cache = new ObjectCache( self::GROUP );

		$value = $cache->remember( 'key', static fn() => 'computed' );

		self::assertSame( 'computed', $value );
		self::assertSame( 'computed', $cache->get( 'key' ) );
	}

	public function test_remember_returns_the_cached_value_without_recomputing(): void {
		$cache = new ObjectCache( self::GROUP );
		$calls = 0;
		$compute = function () use ( &$calls ): string {
			++$calls;
			return 'computed';
		};

		self::assertSame( 'computed', $cache->remember( 'key', $compute ) );
		self::assertSame( 'computed', $cache->remember( 'key', $compute ) );
		self::assertSame( 1, $calls );
	}

	public function test_remember_caches_a_falsey_value_without_recomputing(): void {
		$cache = new ObjectCache( self::GROUP );
		$calls = 0;
		$compute = function () use ( &$calls ): bool {
			++$calls;
			return false;
		};

		self::assertFalse( $cache->remember( 'flag', $compute ) );
		self::assertFalse( $cache->remember( 'flag', $compute ) );
		self::assertSame( 1, $calls );
	}

	public function test_get_multiple_returns_values_keyed_by_key_with_misses_as_false(): void {
		$cache = new ObjectCache( self::GROUP );
		$cache->set( 'a', 1 );
		$cache->set( 'c', 3 );

		$result = $cache->get_multiple( array( 'a', 'b', 'c' ) );

		self::assertSame( 1, $result['a'] );
		self::assertFalse( $result['b'] );
		self::assertSame( 3, $result['c'] );
	}

	public function test_delete_removes_one_key_without_flushing_the_group(): void {
		$cache = new ObjectCache( self::GROUP );
		$cache->set( 'a', 1 );
		$cache->set( 'b', 2 );

		$cache->delete( 'a' );

		self::assertSame( 'gone', $cache->get( 'a', 'gone' ) );
		self::assertSame( 2, $cache->get( 'b' ) );
	}

	public function test_remember_does_not_let_a_value_survive_a_flush_during_its_callback(): void {
		$cache = new ObjectCache( self::GROUP );

		$value = $cache->remember( 'key', function () use ( $cache ): string {
			$cache->flush();
			return 'computed';
		} );

		// The caller still gets the computed value, but it was stored under the pre-flush generation, so
		// the flush leaves it unreachable rather than letting it outlive the invalidation it raced.
		self::assertSame( 'computed', $value );
		self::assertSame( 'gone', $cache->get( 'key', 'gone' ) );
	}

	public function test_flush_invalidates_the_whole_group(): void {
		$cache = new ObjectCache( self::GROUP );
		$cache->set( 'a', 1 );
		$cache->set( 'b', 2 );

		$cache->flush();

		self::assertSame( 'gone', $cache->get( 'a', 'gone' ) );
		self::assertSame( 'gone', $cache->get( 'b', 'gone' ) );
	}

	public function test_flush_generation_suffix_is_not_autoloaded(): void {
		( new ObjectCache( self::GROUP ) )->flush();

		\wp_cache_delete( 'alloptions', 'options' );

		self::assertArrayNotHasKey( self::GROUP . '_object_cache_generation', \wp_load_alloptions() );
	}
}
