<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Integration\Storage;

use DeepWebSolutions\Framework\Utilities\Storage\OptionsStore;
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

	public function test_has_and_delete(): void {
		$store = new OptionsStore( self::OPTION_KEY );
		$store->set( 'k', 'v' );

		self::assertTrue( $store->has( 'k' ) );
		self::assertTrue( $store->delete( 'k' ) );
		self::assertFalse( $store->has( 'k' ) );
		self::assertFalse( $store->delete( 'k' ) );
	}

	public function test_get_all_and_clear(): void {
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
}
