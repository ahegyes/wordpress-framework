<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Unit\AdminNotices;

use DeepWebSolutions\Framework\Utilities\AdminNotices\AdminNoticesService;
use DeepWebSolutions\Framework\Utilities\AdminNotices\ValueObjects\AdminNotice;
use DeepWebSolutions\Framework\Utilities\AdminNotices\ValueObjects\NoticeType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( AdminNoticesService::class )]
#[UsesClass( AdminNotice::class )]
#[UsesClass( NoticeType::class )]
final class AdminNoticesServiceTest extends TestCase {
	public function test_add_notice_stores_by_id(): void {
		$service = new AdminNoticesService();
		$notice  = new AdminNotice( 'x', 'msg' );

		$service->add_notice( $notice );

		self::assertSame( array( 'x' => $notice ), $service->notices );
	}

	public function test_add_notice_replaces_existing_with_same_id(): void {
		$service = new AdminNoticesService();
		$service->add_notice( new AdminNotice( 'x', 'first' ) );
		$service->add_notice( new AdminNotice( 'x', 'second', NoticeType::Warning ) );

		$notices = $service->notices;
		self::assertCount( 1, $notices );
		self::assertSame( 'second', $notices['x']->message );
		self::assertSame( NoticeType::Warning, $notices['x']->type );
	}

	public function test_remove_notice_returns_true_when_removed(): void {
		$service = new AdminNoticesService();
		$service->add_notice( new AdminNotice( 'x', 'msg' ) );

		self::assertTrue( $service->remove_notice( 'x' ) );
		self::assertSame( array(), $service->notices );
	}

	public function test_remove_notice_returns_false_when_not_found(): void {
		$service = new AdminNoticesService();

		self::assertFalse( $service->remove_notice( 'missing' ) );
	}

	public function test_notices_returns_empty_array_initially(): void {
		$service = new AdminNoticesService();

		self::assertSame( array(), $service->notices );
	}
}
