<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Unit\AdminNotices;

use DeepWebSolutions\Framework\Utilities\AdminNotices\NoticeStore;
use DeepWebSolutions\Framework\Utilities\AdminNotices\ValueObjects\AdminNotice;
use DeepWebSolutions\Framework\Utilities\AdminNotices\NoticeType;
use DeepWebSolutions\Framework\Storage\MemoryStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( NoticeStore::class )]
#[UsesClass( AdminNotice::class )]
#[UsesClass( NoticeType::class )]
#[UsesClass( MemoryStore::class )]
final class NoticeStoreTest extends TestCase {
	public function test_add_then_get_round_trips_the_notice(): void {
		$store  = new NoticeStore( new MemoryStore() );
		$notice = new AdminNotice( 'welcome', 'Hello', NoticeType::Success, is_persistent: true );

		$store->add( $notice );

		self::assertEquals( $notice, $store->get( 'welcome' ) );
	}

	public function test_get_returns_null_for_absent_id(): void {
		$store = new NoticeStore( new MemoryStore() );

		self::assertNull( $store->get( 'missing' ) );
	}

	public function test_has_reflects_presence(): void {
		$store = new NoticeStore( new MemoryStore() );
		$store->add( new AdminNotice( 'x', 'msg' ) );

		self::assertTrue( $store->has( 'x' ) );
		self::assertFalse( $store->has( 'y' ) );
	}

	public function test_remove_returns_true_when_present_and_false_when_absent(): void {
		$store = new NoticeStore( new MemoryStore() );
		$store->add( new AdminNotice( 'x', 'msg' ) );

		self::assertTrue( $store->remove( 'x' ) );
		self::assertFalse( $store->has( 'x' ) );
		self::assertFalse( $store->remove( 'x' ) );
	}

	public function test_get_all_returns_id_keyed_notices(): void {
		$store = new NoticeStore( new MemoryStore() );
		$a     = new AdminNotice( 'a', 'first' );
		$b     = new AdminNotice( 'b', 'second', NoticeType::Warning );
		$store->add( $a );
		$store->add( $b );

		$all = $store->get_all();

		self::assertEquals( array( 'a' => $a, 'b' => $b ), $all );
	}

	public function test_get_all_is_empty_initially(): void {
		$store = new NoticeStore( new MemoryStore() );

		self::assertSame( array(), $store->get_all() );
	}

	public function test_get_all_skips_a_non_array_entry(): void {
		$backing = new MemoryStore();
		$backing->set( 'bad', 'not-an-array' );
		$store = new NoticeStore( $backing );

		self::assertSame( array(), $store->get_all() );
		self::assertNull( $store->get( 'bad' ) );
	}

	public function test_get_all_skips_an_entry_missing_required_fields(): void {
		$backing = new MemoryStore();
		$backing->set( 'no-message', array( 'id' => 'no-message' ) );
		$backing->set( 'no-id', array( 'message' => 'orphan' ) );
		$store = new NoticeStore( $backing );

		self::assertSame( array(), $store->get_all() );
	}

	public function test_get_all_skips_an_entry_whose_id_mismatches_its_key(): void {
		$backing = new MemoryStore();
		$backing->set( 'stored-key', array( 'id' => 'different-id', 'message' => 'm' ) );
		$store = new NoticeStore( $backing );

		self::assertSame( array(), $store->get_all() );
		self::assertNull( $store->get( 'stored-key' ) );
	}

	public function test_clear_empties_the_store(): void {
		$store = new NoticeStore( new MemoryStore() );
		$store->add( new AdminNotice( 'x', 'msg' ) );

		$store->clear();

		self::assertSame( array(), $store->get_all() );
	}

	public function test_get_all_skips_an_entry_with_an_empty_id(): void {
		$backing = new MemoryStore();
		$backing->set( '', array( 'id' => '', 'message' => 'm' ) );
		$store = new NoticeStore( $backing );

		self::assertSame( array(), $store->get_all() );
		self::assertNull( $store->get( '' ) );
	}
}
