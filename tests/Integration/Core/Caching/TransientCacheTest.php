<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Core\Caching;

use DeepWebSolutions\Framework\Caching\InvalidCacheKeyException;
use DeepWebSolutions\Framework\Caching\TransientCache;
use DeepWebSolutions\Framework\Shared\Exception\InvalidArgumentException;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesStorageTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\InvalidArgumentException as PsrInvalidArgumentException;

#[CoversClass( TransientCache::class )]
#[CoversClass( InvalidCacheKeyException::class )]
final class TransientCacheTest extends TestCase {
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

	public function test_construction_reads_and_writes_nothing(): void {
		global $wpdb;

		$queries = $wpdb->num_queries;
		new TransientCache( \str_pad( "{$this->prefix}_", 140, 'p' ) );

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
			'bare-string' => array( 'Value.' ),
			'list'        => array( array( 'Value.', 'Generation.' ) ),
			'value-only'  => array( array( 'value' => 'Value.' ) ),
			'extra-key'   => array(
				array(
					'value'      => 'Value.',
					'generation' => 'Generation.',
					'extra'      => true,
				),
			),
		);
	}

	#[DataProvider( 'foreign_values' )]
	public function test_a_value_stored_without_the_envelope_reads_as_a_miss( mixed $stored ): void {
		$cache = $this->cache();
		$cache->set( 'other', 'Value.' );
		\set_transient( "{$this->prefix}_" . \md5( 'key' ), $stored, \HOUR_IN_SECONDS );

		self::assertSame( 'Default.', $cache->get( 'key', 'Default.' ) );
		self::assertFalse( $cache->has( 'key' ) );
	}

	public function test_a_filtered_scalar_reads_as_a_miss(): void {
		\add_filter( "pre_transient_{$this->prefix}_" . \md5( 'key' ), static fn (): string => 'Filtered.' );

		self::assertSame( 'Default.', $this->cache()->get( 'key', 'Default.' ) );
	}

	protected function cache(): TransientCache {
		return new TransientCache( "{$this->prefix}_" );
	}

	protected function stored(): array {
		global $wpdb;

		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT option_name, autoload FROM %i WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s ORDER BY option_name', $wpdb->options, $wpdb->esc_like( $this->prefix ) . '%', $wpdb->esc_like( "_transient_$this->prefix" ) . '%', $wpdb->esc_like( "_transient_timeout_$this->prefix" ) . '%' ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Inspects the stored rows.

		$stored = \array_column( $rows, 'autoload', 'option_name' );
		\ksort( $stored );

		return $stored;
	}
}
