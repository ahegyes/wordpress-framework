<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Integration\Caching;

use DeepWebSolutions\Framework\Utilities\Caching\TransientCache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( TransientCache::class )]
final class TransientCacheTest extends TestCase {
	private const PREFIX  = 'dws_test_cache';
	private const PREFIX2 = 'dws_test_cache_other';

	protected function setUp(): void {
		parent::setUp();
		$this->purge( self::PREFIX );
		$this->purge( self::PREFIX2 );
		\wp_cache_flush();
	}

	protected function tearDown(): void {
		$this->purge( self::PREFIX );
		$this->purge( self::PREFIX2 );
		\wp_cache_flush();
		parent::tearDown();
	}

	public function test_set_then_get_round_trips_an_array_payload(): void {
		$cache = new TransientCache( self::PREFIX );
		$cache->set( 'k', array( 'a' => 1, 'b' => 2 ), HOUR_IN_SECONDS );

		self::assertSame( array( 'a' => 1, 'b' => 2 ), $cache->get( 'k' ) );
	}

	public function test_get_returns_default_for_a_missing_key(): void {
		self::assertSame( 'fallback', ( new TransientCache( self::PREFIX ) )->get( 'missing', 'fallback' ) );
	}

	/**
	 * @return array<string, array{mixed}>
	 */
	public static function falsy_values(): array {
		return array(
			'false'        => array( false ),
			'zero'         => array( 0 ),
			'empty-string' => array( '' ),
			'null'         => array( null ),
		);
	}

	#[DataProvider( 'falsy_values' )]
	public function test_get_returns_a_stored_falsy_value_as_a_hit( mixed $value ): void {
		$cache = new TransientCache( self::PREFIX );
		$cache->set( 'k', $value, HOUR_IN_SECONDS );

		self::assertSame( $value, $cache->get( 'k', 'sentinel' ) );
	}

	public function test_get_treats_a_foreign_raw_transient_as_a_miss(): void {
		// A value written outside the envelope must not be mistaken for a hit (and must not warn).
		\set_transient( self::PREFIX . '/k__1', 'bare-string' );

		self::assertSame( 'default', ( new TransientCache( self::PREFIX ) )->get( 'k', 'default' ) );
	}

	public function test_get_unwraps_exactly_one_envelope_layer(): void {
		$cache  = new TransientCache( self::PREFIX );
		$shaped = array( TransientCache::PAYLOAD => 'inner' );
		$cache->set( 'k', $shaped, HOUR_IN_SECONDS );

		self::assertSame( $shaped, $cache->get( 'k' ) );
	}

	public function test_set_overwrites_an_existing_value(): void {
		$cache = new TransientCache( self::PREFIX );
		$cache->set( 'k', 'a', HOUR_IN_SECONDS );
		$cache->set( 'k', 'b', HOUR_IN_SECONDS );

		self::assertSame( 'b', $cache->get( 'k' ) );
	}

	public function test_set_with_a_positive_ttl_writes_a_timeout_row(): void {
		$cache = new TransientCache( self::PREFIX );
		$cache->set( 'ttl', 'v', HOUR_IN_SECONDS );

		self::assertNotFalse( \get_option( '_transient_timeout_' . self::PREFIX . '/ttl__1' ) );
	}

	/**
	 * @return array<string, array{int}>
	 */
	public static function non_positive_expirations(): array {
		return array(
			'zero'     => array( 0 ),
			'negative' => array( -1 ),
		);
	}

	#[DataProvider( 'non_positive_expirations' )]
	public function test_set_with_a_non_positive_expiration_warns_and_stores_nothing( int $expiration ): void {
		$fired = 0;
		$spy   = static function () use ( &$fired ) {
			++$fired;
		};
		\add_filter( 'doing_it_wrong_trigger_error', '__return_false' );
		\add_action( 'doing_it_wrong_run', $spy );

		try {
			$cache = new TransientCache( self::PREFIX );

			$cache->set( 'k', 'v', $expiration );

			// The write is skipped, so the transient never lands and a read is a miss.
			self::assertFalse( \get_transient( self::PREFIX . '/k__1' ) );
			self::assertSame( 'default', $cache->get( 'k', 'default' ) );
			self::assertGreaterThan( 0, $fired );
		} finally {
			\remove_action( 'doing_it_wrong_run', $spy );
			\remove_filter( 'doing_it_wrong_trigger_error', '__return_false' );
		}
	}

	public function test_remember_with_a_non_positive_expiration_runs_once_warns_and_caches_nothing(): void {
		$fired = 0;
		$spy   = static function () use ( &$fired ) {
			++$fired;
		};
		\add_filter( 'doing_it_wrong_trigger_error', '__return_false' );
		\add_action( 'doing_it_wrong_run', $spy );

		try {
			$cache  = new TransientCache( self::PREFIX );
			$calls  = 0;
			$result = $cache->remember(
				'k',
				static function () use ( &$calls ) {
					++$calls;
					return 'fresh';
				},
				0
			);

			self::assertSame( 'fresh', $result );
			self::assertSame( 1, $calls );
			self::assertGreaterThan( 0, $fired );
			self::assertSame( 'miss', $cache->get( 'k', 'miss' ) );
		} finally {
			\remove_action( 'doing_it_wrong_run', $spy );
			\remove_filter( 'doing_it_wrong_trigger_error', '__return_false' );
		}
	}

	public function test_get_returns_default_after_expiry_and_remember_recomputes(): void {
		$cache = new TransientCache( self::PREFIX );
		$cache->set( 'k', 'v', HOUR_IN_SECONDS );

		// Backdate the timeout so the next read sees an expired transient (no real sleep).
		\update_option( '_transient_timeout_' . self::PREFIX . '/k__1', \time() - 1 );
		self::assertSame( 'sentinel', $cache->get( 'k', 'sentinel' ) );

		$calls  = 0;
		$result = $cache->remember(
			'k',
			static function () use ( &$calls ) {
				++$calls;
				return 'fresh';
			},
			HOUR_IN_SECONDS
		);

		self::assertSame( 'fresh', $result );
		self::assertSame( 1, $calls );
	}

	public function test_delete_returns_true_when_present_and_false_when_absent(): void {
		$cache = new TransientCache( self::PREFIX );
		$cache->set( 'k', 'v', HOUR_IN_SECONDS );

		self::assertTrue( $cache->delete( 'k' ) );
		self::assertSame( 'gone', $cache->get( 'k', 'gone' ) );
		self::assertFalse( $cache->delete( 'k' ) );
	}

	public function test_remember_runs_the_callback_once_on_a_miss(): void {
		$cache = new TransientCache( self::PREFIX );

		$calls  = 0;
		$result = $cache->remember(
			'k',
			static function () use ( &$calls ) {
				++$calls;
				return 'computed';
			},
			HOUR_IN_SECONDS
		);

		self::assertSame( 'computed', $result );
		self::assertSame( 1, $calls );
		self::assertSame( 'computed', $cache->get( 'k' ) );
	}

	public function test_remember_does_not_rerun_the_callback_on_a_hit(): void {
		$cache    = new TransientCache( self::PREFIX );
		$calls    = 0;
		$callback = static function () use ( &$calls ) {
			++$calls;
			return 'v';
		};

		$cache->remember( 'k', $callback, HOUR_IN_SECONDS );
		$cache->remember( 'k', $callback, HOUR_IN_SECONDS );

		self::assertSame( 1, $calls );
	}

	public function test_remember_caches_a_falsy_result_without_rerunning(): void {
		$cache    = new TransientCache( self::PREFIX );
		$calls    = 0;
		$callback = static function () use ( &$calls ) {
			++$calls;
			return false;
		};

		self::assertFalse( $cache->remember( 'k', $callback, HOUR_IN_SECONDS ) );
		self::assertFalse( $cache->remember( 'k', $callback, HOUR_IN_SECONDS ) );
		self::assertSame( 1, $calls );
	}

	public function test_remember_propagates_a_callback_exception_and_caches_nothing(): void {
		$cache = new TransientCache( self::PREFIX );

		try {
			$cache->remember(
				'k',
				static function (): never {
					throw new \RuntimeException( 'boom' );
				},
				HOUR_IN_SECONDS
			);
			self::fail( 'Expected the callback exception to propagate.' );
		} catch ( \RuntimeException $e ) {
			self::assertSame( 'boom', $e->getMessage() );
		}

		self::assertSame( 'none', $cache->get( 'k', 'none' ) );
	}

	public function test_flush_bumps_the_suffix_and_invalidates_the_group(): void {
		$cache = new TransientCache( self::PREFIX );
		$cache->set( 'k', 'v', HOUR_IN_SECONDS );

		// Fresh prefix: the suffix defaults to 1 lazily, so no option row exists yet.
		self::assertFalse( \get_option( self::PREFIX . '_cache_invalidation_suffix' ) );
		self::assertNotFalse( \get_transient( self::PREFIX . '/k__1' ) );

		$cache->flush();

		self::assertSame( 2, (int) \get_option( self::PREFIX . '_cache_invalidation_suffix' ) );
		self::assertSame( 'default', $cache->get( 'k', 'default' ) );
	}

	public function test_a_corrupt_suffix_degrades_to_generation_one(): void {
		\update_option( self::PREFIX . '_cache_invalidation_suffix', 'foo' );

		$cache = new TransientCache( self::PREFIX );
		$cache->set( 'k', 'v', HOUR_IN_SECONDS );

		self::assertNotFalse( \get_transient( self::PREFIX . '/k__1' ) );
		self::assertSame( 'v', $cache->get( 'k' ) );
	}

	public function test_prefixes_are_isolated_from_each_other(): void {
		$a = new TransientCache( self::PREFIX );
		$b = new TransientCache( self::PREFIX2 );

		$a->set( 'k', 'from-a', HOUR_IN_SECONDS );
		self::assertSame( 'none', $b->get( 'k', 'none' ) );

		$b->set( 'k', 'from-b', HOUR_IN_SECONDS );
		$a->flush();

		self::assertSame( 'from-b', $b->get( 'k', 'none' ) );
		self::assertSame( 'none', $a->get( 'k', 'none' ) );
	}

	public function test_an_over_long_key_triggers_the_guard_and_skips_caching(): void {
		$fired = 0;
		$spy   = static function () use ( &$fired ) {
			++$fired;
		};
		\add_filter( 'doing_it_wrong_trigger_error', '__return_false' );
		\add_action( 'doing_it_wrong_run', $spy );

		try {
			$cache = new TransientCache( self::PREFIX );
			$long  = \str_repeat( 'x', 300 );

			$cache->set( $long, 'v', HOUR_IN_SECONDS );

			// set() must skip the write entirely — nothing is stored under the over-long name.
			self::assertFalse( \get_transient( self::PREFIX . '/' . $long . '__1' ) );
			self::assertSame( 'default', $cache->get( $long, 'default' ) );
			self::assertFalse( $cache->delete( $long ) );
			self::assertGreaterThan( 0, $fired );
		} finally {
			\remove_action( 'doing_it_wrong_run', $spy );
			\remove_filter( 'doing_it_wrong_trigger_error', '__return_false' );
		}
	}

	private function purge( string $prefix ): void {
		global $wpdb;

		$value_like   = $wpdb->esc_like( '_transient_' . $prefix . '/' ) . '%';
		$timeout_like = $wpdb->esc_like( '_transient_timeout_' . $prefix . '/' ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$value_like,
				$timeout_like
			)
		);

		\delete_option( $prefix . '_cache_invalidation_suffix' );
	}
}
