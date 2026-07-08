<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Storage\Tests\Integration;

use DeepWebSolutions\Framework\Storage\OptionsStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( OptionsStore::class )]
final class OptionsStoreTest extends TestCase {
	private const OPTION_KEY = 'dws_test_options_store';

	protected function setUp(): void {
		parent::setUp();
		\delete_option( self::OPTION_KEY );
	}

	protected function tearDown(): void {
		\delete_option( self::OPTION_KEY );
		parent::tearDown();
	}

	public function test_set_then_get_round_trips_across_instances(): void {
		( new OptionsStore( self::OPTION_KEY ) )->set( 'k', 'v' );

		// A fresh instance reads the persisted wp_options row.
		self::assertSame( 'v', ( new OptionsStore( self::OPTION_KEY ) )->get( 'k' ) );
	}

	public function test_get_returns_default_for_missing_key(): void {
		$store = new OptionsStore( self::OPTION_KEY );

		self::assertSame( 'fallback', $store->get( 'missing', 'fallback' ) );
	}

	public function test_get_returns_stored_null_over_non_null_default(): void {
		$store = new OptionsStore( self::OPTION_KEY );
		$store->set( 'k', null );

		self::assertTrue( $store->has( 'k' ) );
		self::assertNull( $store->get( 'k', 'sentinel' ) );
	}

	public function test_has_reports_a_stored_key_and_not_a_missing_one(): void {
		$store = new OptionsStore( self::OPTION_KEY );
		$store->set( 'k', 'v' );

		self::assertTrue( $store->has( 'k' ) );
		self::assertFalse( $store->has( 'missing' ) );
	}

	public function test_delete_removes_a_stored_key_and_reports_a_missing_one(): void {
		$store = new OptionsStore( self::OPTION_KEY );
		$store->set( 'k', 'v' );

		self::assertTrue( $store->delete( 'k' ) );
		self::assertFalse( $store->has( 'k' ) );
		self::assertFalse( $store->delete( 'k' ) );
	}

	public function test_get_all_returns_every_stored_entry(): void {
		$store = new OptionsStore( self::OPTION_KEY );
		$store->set( 'a', 1 );
		$store->set( 'b', 2 );

		self::assertSame(
			array(
				'a' => 1,
				'b' => 2,
			),
			$store->get_all(),
		);
	}

	public function test_clear_empties_the_store(): void {
		$store = new OptionsStore( self::OPTION_KEY );
		$store->set( 'a', 1 );
		$store->set( 'b', 2 );

		$store->clear();

		self::assertSame( array(), $store->get_all() );
	}

	public function test_corrupted_option_value_falls_back_to_empty(): void {
		// A non-array value left under the key by other code must not break reads.
		\update_option( self::OPTION_KEY, 'not-an-array' );
		$store = new OptionsStore( self::OPTION_KEY );

		self::assertSame( array(), $store->get_all() );
		self::assertSame( 'default', $store->get( 'k', 'default' ) );
	}

	public function test_autoload_false_persists_value_without_autoloading(): void {
		( new OptionsStore( self::OPTION_KEY, autoload: false ) )->set( 'k', 'v' );

		// The value still round-trips across instances...
		self::assertSame( 'v', ( new OptionsStore( self::OPTION_KEY ) )->get( 'k' ) );

		// ...but the option is excluded from the autoloaded set.
		\wp_cache_delete( 'alloptions', 'options' );
		self::assertArrayNotHasKey( self::OPTION_KEY, \wp_load_alloptions() );
	}

	public function test_autoload_true_is_autoloaded(): void {
		( new OptionsStore( self::OPTION_KEY, autoload: true ) )->set( 'k', 'v' );

		\wp_cache_delete( 'alloptions', 'options' );
		self::assertArrayHasKey( self::OPTION_KEY, \wp_load_alloptions() );
	}
}
