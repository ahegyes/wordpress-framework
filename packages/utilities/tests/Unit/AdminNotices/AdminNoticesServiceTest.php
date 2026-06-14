<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Unit\AdminNotices;

use DeepWebSolutions\Framework\Utilities\AdminNotices\AdminNoticesService;
use DeepWebSolutions\Framework\Utilities\AdminNotices\NoticeStore;
use DeepWebSolutions\Framework\Utilities\AdminNotices\ValueObjects\AdminNotice;
use DeepWebSolutions\Framework\Utilities\AdminNotices\ValueObjects\NoticeType;
use DeepWebSolutions\Framework\Utilities\Storage\MemoryStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( AdminNoticesService::class )]
#[UsesClass( NoticeStore::class )]
#[UsesClass( AdminNotice::class )]
#[UsesClass( NoticeType::class )]
#[UsesClass( MemoryStore::class )]
final class AdminNoticesServiceTest extends TestCase {
	public function test_constructs_with_a_default_memory_store(): void {
		$service = new AdminNoticesService();

		self::assertArrayHasKey( 'memory', $service->stores );
		self::assertInstanceOf( NoticeStore::class, $service->stores['memory'] );
	}

	public function test_empty_stores_array_registers_no_stores(): void {
		$service = new AdminNoticesService( array() );

		self::assertSame( array(), $service->stores );
	}

	public function test_add_notice_routes_to_the_default_store(): void {
		$service = new AdminNoticesService();
		$notice  = new AdminNotice( 'x', 'msg' );

		$service->add_notice( $notice );

		self::assertEquals( $notice, $service->stores['memory']->get( 'x' ) );
	}

	public function test_add_notice_routes_to_a_named_store(): void {
		$service = new AdminNoticesService(
			array(
				'memory' => new NoticeStore( new MemoryStore() ),
				'extra'  => new NoticeStore( new MemoryStore() ),
			),
		);
		$notice = new AdminNotice( 'x', 'msg' );

		$service->add_notice( $notice, 'extra' );

		self::assertEquals( $notice, $service->stores['extra']->get( 'x' ) );
		self::assertNull( $service->stores['memory']->get( 'x' ) );
	}

	public function test_remove_notice_from_a_named_store(): void {
		$service = new AdminNoticesService();
		$service->add_notice( new AdminNotice( 'x', 'msg' ) );

		self::assertTrue( $service->remove_notice( 'x', 'memory' ) );
		self::assertFalse( $service->stores['memory']->has( 'x' ) );
	}

	public function test_remove_notice_with_null_store_searches_every_store(): void {
		$service = new AdminNoticesService(
			array(
				'memory' => new NoticeStore( new MemoryStore() ),
				'extra'  => new NoticeStore( new MemoryStore() ),
			),
		);
		$service->add_notice( new AdminNotice( 'x', 'msg' ), 'extra' );

		self::assertTrue( $service->remove_notice( 'x' ) );
		self::assertFalse( $service->stores['extra']->has( 'x' ) );
	}

	public function test_remove_notice_returns_false_when_absent_everywhere(): void {
		$service = new AdminNoticesService();

		self::assertFalse( $service->remove_notice( 'missing' ) );
	}

	public function test_remove_notice_named_targets_only_that_store(): void {
		$service = new AdminNoticesService(
			array(
				'memory' => new NoticeStore( new MemoryStore() ),
				'extra'  => new NoticeStore( new MemoryStore() ),
			),
		);
		$service->add_notice( new AdminNotice( 'x', 'msg' ), 'memory' );
		$service->add_notice( new AdminNotice( 'x', 'msg' ), 'extra' );

		// An unknown store name on removal is a benign false (no _doing_it_wrong, unlike add_notice).
		self::assertFalse( $service->remove_notice( 'x', 'user-meta' ) );

		self::assertTrue( $service->remove_notice( 'x', 'memory' ) );
		self::assertFalse( $service->stores['memory']->has( 'x' ) );
		self::assertTrue( $service->stores['extra']->has( 'x' ) );
	}
}
