<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Core\Caching;

use DeepWebSolutions\Framework\Core\Caching\ObjectCache;
use DeepWebSolutions\Framework\Shared\Exception\InvalidArgumentException;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesStorageTrait;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( ObjectCache::class )]
final class ObjectCacheTest extends TestCase {
	use IsolatesHooksTrait;
	use IsolatesStorageTrait;

	protected \WP_Object_Cache $replaced;

	protected \WP_Object_Cache $spy;

	#[Before]
	protected function record_the_object_cache(): void {
		global $wp_object_cache;

		$this->replaced = $wp_object_cache;
		$this->spy      = new class() extends \WP_Object_Cache {
			public array $calls = array();

			public array $refused = array();

			protected int $depth = 0;

			#[\Override]
			public function get( $key, $group = 'default', $force = false, &$found = null ) {
				$this->record( 'get', $group, $key );

				return parent::get( $key, $group, $force, $found );
			}

			#[\Override]
			public function get_multiple( $keys, $group = 'default', $force = false ) {
				$this->record( 'get_multiple', $group, $keys );

				return $this->nested( fn (): array => parent::get_multiple( $keys, $group, $force ) );
			}

			#[\Override]
			public function set( $key, $data, $group = 'default', $expire = 0 ) {
				$this->record( 'set', $group, $key, $expire );

				return $this->refuses( 'set', $key ) ? false : parent::set( $key, $data, $group, $expire );
			}

			#[\Override]
			public function set_multiple( array $data, $group = '', $expire = 0 ) {
				$this->record( 'set_multiple', $group, \array_keys( $data ), $expire );

				return $this->nested( fn (): array => parent::set_multiple( $data, $group, $expire ) );
			}

			#[\Override]
			public function delete( $key, $group = 'default', $deprecated = false ) {
				$this->record( 'delete', $group, $key );

				return $this->refuses( 'delete', $key ) ? false : parent::delete( $key, $group, $deprecated );
			}

			#[\Override]
			public function delete_multiple( array $keys, $group = '' ) {
				$this->record( 'delete_multiple', $group, $keys );

				return $this->nested( fn (): array => parent::delete_multiple( $keys, $group ) );
			}

			#[\Override]
			public function flush_group( $group ) {
				$this->record( 'flush_group', $group, null );

				return parent::flush_group( $group );
			}

			protected function nested( \Closure $call ): array {
				++$this->depth;
				try {
					return $call();
				} finally {
					--$this->depth;
				}
			}

			protected function record( string $method, mixed $group, mixed $key, mixed $expire = null ): void {
				if ( 0 === $this->depth ) {
					$this->calls[] = array( $method, $group, $key, $expire );
				}
			}

			protected function refuses( string $method, mixed $key ): bool {
				$refused = $this->refused[ $method ] ?? false;

				return true === $refused || ( \is_array( $refused ) && \in_array( (string) $key, $refused, true ) );
			}
		};
		$this->spy->add_global_groups( \array_keys( $wp_object_cache->global_groups ) );
		$wp_object_cache = $this->spy;
	}

	#[After]
	protected function restore_the_object_cache(): void {
		$GLOBALS['wp_object_cache'] = $this->replaced;
		\wp_cache_flush();
	}

	public static function invalid_groups(): array {
		return array(
			'empty'            => array( '' ),
			'zero'             => array( '0' ),
			'digit-first'      => array( '1acme' ),
			'underscore-first' => array( '_acme' ),
			'uppercase'        => array( 'Acme_cache' ),
			'colon'            => array( 'acme:cache' ),
			'space'            => array( 'acme cache' ),
			'dot'              => array( 'acme.cache' ),
			'leading-space'    => array( ' acme' ),
			'trailing-newline' => array( "acme\n" ),
			'overlong'         => array( 'a' . \str_repeat( 'b', 64 ) ),
		);
	}

	#[DataProvider( 'invalid_groups' )]
	public function test_a_group_that_is_not_a_lowercase_identifier_of_up_to_64_characters_is_rejected( string $group ): void {
		$this->expectException( InvalidArgumentException::class );

		new ObjectCache( $group );
	}

	public function test_a_valid_group_is_accepted_without_touching_the_object_cache(): void {
		new ObjectCache( 'a' );
		new ObjectCache( 'a' . \str_repeat( 'b', 63 ) );
		new ObjectCache( 'acme_cache-2' );

		self::assertSame( array(), $this->spy->calls );
	}

	public function test_an_entry_is_stored_under_the_hash_of_its_key_with_the_salt_it_was_written_under(): void {
		$this->cache()->set( 'key', array( 'a' => 1 ) );

		self::assertSame(
			array(
				'value'      => \maybe_serialize( array( 'a' => 1 ) ),
				'generation' => \wp_cache_get( 'last_changed', $this->group() ),
			),
			\wp_cache_get( \md5( 'key' ), $this->group() )
		);
		self::assertEqualsCanonicalizing( array( 'last_changed', \md5( 'key' ) ), \array_keys( $this->spy->cache[ $this->group() ] ) );
	}

	public function test_a_key_named_last_changed_leaves_the_salt_alone(): void {
		$cache = $this->cache();
		$cache->set( 'other', 'Other.' );
		$salt = \wp_cache_get( 'last_changed', $this->group() );

		$cache->set( 'last_changed', 'Value.' );

		self::assertSame( $salt, \wp_cache_get( 'last_changed', $this->group() ) );
		self::assertSame( 'Other.', $cache->get( 'other' ) );
	}

	public static function lifetimes(): array {
		return array(
			'null-until-evicted' => array( null, 0 ),
			'seconds'            => array( 120, 120 ),
			'hours-interval'     => array( new \DateInterval( 'PT2H' ), 7200 ),
		);
	}

	#[DataProvider( 'lifetimes' )]
	public function test_an_entry_reaches_the_object_cache_with_its_lifetime( null|int|\DateInterval $ttl, int $expire ): void {
		$cache = $this->cache();

		$cache->set( 'key', 'Value.', $ttl );
		$cache->setMultiple( array( 'other' => 'Value.' ), $ttl );

		self::assertSame(
			array(
				array( 'set', \md5( 'key' ), $expire ),
				array( 'set_multiple', array( \md5( 'other' ) ), $expire ),
			),
			$this->entry_writes()
		);
	}

	public function test_reads_fire_nothing_and_only_the_first_write_starts_the_salt(): void {
		$fired = $this->record_last_changed();
		$cache = $this->cache();

		$cache->get( 'key' );
		$cache->has( 'key' );
		$cache->getMultiple( array( 'key' ) );
		self::assertSame( array(), $fired->getArrayCopy() );

		$cache->set( 'key', 'Value.' );
		$cache->setMultiple( array( 'other' => 'Value.' ) );
		$cache->set( 'more', 'Value.' );

		self::assertSame( array( array( $this->group(), false ) ), $fired->getArrayCopy() );
	}

	public static function unusable_salts(): array {
		return array(
			'empty'   => array( '' ),
			'integer' => array( 5 ),
			'array'   => array( array( 'token' ) ),
		);
	}

	#[DataProvider( 'unusable_salts' )]
	public function test_a_salt_holding_no_non_empty_string_reads_as_none_until_a_write_starts_one( mixed $stored ): void {
		\wp_cache_set( 'last_changed', $stored, $this->group() );
		\wp_cache_set(
			\md5( 'key' ),
			array(
				'value'      => 'Value.',
				'generation' => $stored,
			),
			$this->group()
		);
		$cache = $this->cache();

		self::assertSame( 'Default.', $cache->get( 'key', 'Default.' ) );
		self::assertTrue( $cache->set( 'other', 'Other.' ) );
		self::assertTrue( $cache->setMultiple( array( 'more' => 'More.' ) ) );
		self::assertIsString( \wp_cache_get( 'last_changed', $this->group() ) );
		self::assertNotSame( '', \wp_cache_get( 'last_changed', $this->group() ) );
		self::assertSame( 'Other.', $cache->get( 'other' ) );
		self::assertSame( 'More.', $cache->get( 'more' ) );
	}

	public function test_a_first_batch_write_starts_the_salt(): void {
		$fired = $this->record_last_changed();
		$cache = $this->cache();

		self::assertTrue( $cache->setMultiple( array( 'key' => 'Value.' ) ) );

		self::assertSame( array( array( $this->group(), false ) ), $fired->getArrayCopy() );
		self::assertSame( 'Value.', $cache->get( 'key' ) );
	}

	public function test_clearing_fires_the_last_changed_action_and_stores_a_random_salt(): void {
		$fired = $this->record_last_changed();
		$cache = $this->cache();
		$cache->set( 'key', 'Value.' );
		$salt = \wp_cache_get( 'last_changed', $this->group() );

		self::assertTrue( $cache->clear() );
		$first = \wp_cache_get( 'last_changed', $this->group() );
		self::assertTrue( $cache->clear() );
		$second = \wp_cache_get( 'last_changed', $this->group() );

		self::assertMatchesRegularExpression( '/\A[0-9a-f]{16}\z/', $first );
		self::assertMatchesRegularExpression( '/\A[0-9a-f]{16}\z/', $second );
		self::assertNotSame( $first, $second );
		self::assertSame(
			array(
				array( $this->group(), false ),
				array( $this->group(), $salt ),
				array( $this->group(), $first ),
			),
			$fired->getArrayCopy()
		);
		self::assertFalse( $cache->has( 'key' ) );
	}

	// Putting the previous time back acts as microtime() returning the same value within one microsecond.
	public function test_a_clear_whose_last_changed_time_repeats_still_makes_every_entry_miss(): void {
		$cache = $this->cache();
		$cache->set( 'key', 'Value.' );
		\add_action( 'wp_cache_set_last_changed', static fn ( string $group, string $time, mixed $previous ): bool => \wp_cache_set( 'last_changed', $previous, $group ), 10, 3 );

		self::assertTrue( $cache->clear() );

		self::assertFalse( $cache->has( 'key' ) );
	}

	public function test_another_callers_last_changed_change_clears_the_cache(): void {
		$cache = $this->cache();
		$cache->clear();
		$cache->set( 'key', 'Value.' );

		\wp_cache_set_last_changed( $this->group() );

		self::assertFalse( $cache->has( 'key' ) );
	}

	public function test_clearing_leaves_stale_entries_for_the_object_cache_to_evict_and_never_flushes_the_group(): void {
		$cache = $this->cache();
		$cache->set( 'key', 'Value.' );
		$entry = \wp_cache_get( \md5( 'key' ), $this->group() );

		self::assertTrue( $cache->clear() );

		self::assertSame( $entry, \wp_cache_get( \md5( 'key' ), $this->group() ) );
		self::assertNotContains( 'flush_group', \array_column( $this->spy->calls, 0 ) );
	}

	public function test_a_clear_whose_salt_the_object_cache_refuses_returns_false(): void {
		$cache = $this->cache();
		$cache->set( 'key', 'Value.' );
		$this->spy->refused = array( 'set' => array( 'last_changed' ) );

		self::assertFalse( $cache->clear() );
		self::assertSame( 'Value.', $cache->get( 'key' ) );
	}

	public function test_a_write_the_object_cache_refuses_returns_false(): void {
		$cache = $this->cache();
		$cache->set( 'seed', 'Value.' );
		$this->spy->refused = array( 'set' => array( \md5( 'a' ) ) );

		self::assertFalse( $cache->set( 'a', 'A.' ) );
		self::assertFalse(
			$cache->setMultiple(
				array(
					'a' => 'A.',
					'b' => 'B.',
				)
			)
		);
		self::assertFalse( $cache->has( 'a' ) );
		self::assertSame( 'B.', $cache->get( 'b' ) );
	}

	public function test_a_delete_the_object_cache_refuses_returns_false_while_the_entry_remains(): void {
		$cache = $this->cache();
		$cache->setMultiple( \array_fill_keys( array( 'key', 'other' ), 'Value.' ) );
		$this->spy->refused = array( 'delete' => true );

		self::assertFalse( $cache->delete( 'key' ) );
		self::assertTrue( $cache->delete( 'never-set' ) );
		self::assertFalse( $cache->deleteMultiple( array( 'other', 'never-set' ) ) );
		self::assertTrue( $cache->deleteMultiple( array( 'never-set' ) ) );
		self::assertFalse( $cache->set( 'key', 'Newer.', 0 ) );
		self::assertSame( 'Value.', $cache->get( 'key' ) );
		self::assertSame( 'Value.', $cache->get( 'other' ) );
	}

	public function test_the_multiple_methods_make_one_batch_call_each_and_read_the_salt_once(): void {
		$cache = $this->cache();
		$cache->set( 'seed', 'Value.' );
		$this->spy->calls = array();

		$cache->setMultiple(
			array(
				'a' => 'A.',
				'b' => 'B.',
			)
		);
		$cache->getMultiple( array( 'a', 'b', 'c' ) );
		$cache->deleteMultiple( array( 'a', 'b' ) );

		self::assertSame(
			array(
				array( 'get', 'last_changed' ),
				array( 'set_multiple', array( \md5( 'a' ), \md5( 'b' ) ) ),
				array( 'get', 'last_changed' ),
				array( 'get_multiple', array( \md5( 'a' ), \md5( 'b' ), \md5( 'c' ) ) ),
				array( 'delete_multiple', array( \md5( 'a' ), \md5( 'b' ) ) ),
			),
			$this->calls()
		);
	}

	public function test_empty_batches_and_reads_without_a_salt_touch_no_entry(): void {
		$cache = $this->cache();

		$cache->getMultiple( array() );
		$cache->setMultiple( array() );
		$cache->deleteMultiple( array() );
		$cache->get( 'key' );
		$cache->getMultiple( array( 'key', 'other' ) );

		self::assertSame(
			array(
				array( 'get', 'last_changed' ),
				array( 'get', 'last_changed' ),
			),
			$this->calls()
		);
	}

	protected function cache(): ObjectCache {
		return new ObjectCache( $this->group() );
	}

	protected function group(): string {
		return "{$this->prefix}_cache";
	}

	protected function calls(): array {
		$calls = array();
		foreach ( $this->spy->calls as list( $method, $group, $key ) ) {
			if ( $this->group() === $group ) {
				$calls[] = array( $method, $key );
			}
		}

		return $calls;
	}

	protected function entry_writes(): array {
		$writes = array();
		foreach ( $this->spy->calls as list( $method, $group, $key, $expire ) ) {
			if ( $this->group() === $group && \in_array( $method, array( 'set', 'set_multiple' ), true ) && 'last_changed' !== $key ) {
				$writes[] = array( $method, $key, $expire );
			}
		}

		return $writes;
	}

	protected function record_last_changed(): \ArrayObject {
		$fired = new \ArrayObject();
		\add_action(
			'wp_cache_set_last_changed',
			static function ( string $group, string $time, mixed $previous ) use ( $fired ): void {
				$fired[] = array( $group, $previous );
			},
			10,
			3
		);

		return $fired;
	}
}
