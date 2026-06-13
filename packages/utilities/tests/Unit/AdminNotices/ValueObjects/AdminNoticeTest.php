<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Unit\AdminNotices\ValueObjects;

use DeepWebSolutions\Framework\Utilities\AdminNotices\ValueObjects\AdminNotice;
use DeepWebSolutions\Framework\Utilities\AdminNotices\ValueObjects\NoticeType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( AdminNotice::class )]
#[UsesClass( NoticeType::class )]
final class AdminNoticeTest extends TestCase {
	public function test_constructs_with_required_arguments(): void {
		$notice = new AdminNotice( 'my-notice', 'Hello world.' );

		self::assertSame( 'my-notice', $notice->id );
		self::assertSame( 'Hello world.', $notice->message );
		self::assertSame( NoticeType::Info, $notice->type );
		self::assertTrue( $notice->dismissible );
		self::assertSame( 'manage_options', $notice->capability );
	}

	public function test_constructs_with_all_arguments(): void {
		$notice = new AdminNotice(
			id: 'critical',
			message: '<strong>Bad.</strong>',
			type: NoticeType::Error,
			dismissible: false,
			capability: 'activate_plugins',
		);

		self::assertSame( 'critical', $notice->id );
		self::assertSame( '<strong>Bad.</strong>', $notice->message );
		self::assertSame( NoticeType::Error, $notice->type );
		self::assertFalse( $notice->dismissible );
		self::assertSame( 'activate_plugins', $notice->capability );
	}
}
