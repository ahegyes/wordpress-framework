<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Storage\Tests\Unit;

use DeepWebSolutions\Framework\Storage\MemoryStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( MemoryStore::class )]
final class MemoryStoreTest extends TestCase {
	public function test_get_returns_null_for_missing_key(): void {
		$store = new MemoryStore();

		self::assertNull( $store->get( 'missing' ) );
	}

	public function test_set_then_get_round_trips_value(): void {
		$store = new MemoryStore();
		$store->set( 'k', 'v' );

		self::assertSame( 'v', $store->get( 'k' ) );
	}

	public function test_set_overwrites_existing_value(): void {
		$store = new MemoryStore();
		$store->set( 'k', 'first' );
		$store->set( 'k', 'second' );

		self::assertSame( 'second', $store->get( 'k' ) );
	}

	public function test_has_returns_true_for_set_key(): void {
		$store = new MemoryStore();
		$store->set( 'k', 'v' );

		self::assertTrue( $store->has( 'k' ) );
	}

	public function test_has_returns_false_for_missing_key(): void {
		$store = new MemoryStore();

		self::assertFalse( $store->has( 'missing' ) );
	}

	public function test_has_returns_true_when_value_is_null(): void {
		$store = new MemoryStore();
		$store->set( 'k', null );

		self::assertTrue( $store->has( 'k' ) );
		self::assertNull( $store->get( 'k' ) );
	}

	public function test_get_returns_stored_null_over_non_null_default(): void {
		$store = new MemoryStore();
		$store->set( 'k', null );

		self::assertNull( $store->get( 'k', 'sentinel' ) );
	}

	public function test_get_returns_default_only_when_key_absent(): void {
		$store = new MemoryStore();

		self::assertSame( 'sentinel', $store->get( 'missing', 'sentinel' ) );
	}

	public function test_delete_returns_true_when_value_existed(): void {
		$store = new MemoryStore();
		$store->set( 'k', 'v' );

		self::assertTrue( $store->delete( 'k' ) );
		self::assertFalse( $store->has( 'k' ) );
	}

	public function test_delete_returns_false_when_no_value_existed(): void {
		$store = new MemoryStore();

		self::assertFalse( $store->delete( 'missing' ) );
	}

	public function test_get_all_returns_empty_array_initially(): void {
		$store = new MemoryStore();

		self::assertSame( array(), $store->get_all() );
	}

	public function test_get_all_returns_all_entries_indexed_by_key(): void {
		$store = new MemoryStore();
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

	public function test_clear_removes_all_entries(): void {
		$store = new MemoryStore();
		$store->set( 'a', 1 );
		$store->set( 'b', 2 );

		$store->clear();

		self::assertSame( array(), $store->get_all() );
	}

	public function test_stores_objects_by_reference_not_value(): void {
		$store  = new MemoryStore();
		$object = new \stdClass();
		$store->set( 'k', $object );

		self::assertSame( $object, $store->get( 'k' ) );
	}
}
