<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Integration\AdminNotices;

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
	private int $original_user;

	protected function setUp(): void {
		parent::setUp();
		$this->original_user = \get_current_user_id();
	}

	protected function tearDown(): void {
		\wp_set_current_user( $this->original_user );
		parent::tearDown();
	}

	public function test_renders_notice_for_capable_user(): void {
		\wp_set_current_user( $this->administrator_id() );
		$service = new AdminNoticesService();
		$service->add_notice( new AdminNotice( 'welcome', 'Hello admin', NoticeType::Success ) );

		$output = $this->capture_render( $service );

		self::assertStringContainsString( 'Hello admin', $output );
		self::assertStringContainsString( 'dws-notice-welcome', $output );
		self::assertStringContainsString( 'notice-success', $output );
		// dismissible defaults to true.
		self::assertStringContainsString( 'is-dismissible', $output );
	}

	public function test_skips_notice_for_user_without_capability_and_leaves_it_queued(): void {
		\wp_set_current_user( 0 ); // Anonymous lacks manage_options.
		$service = new AdminNoticesService();
		$notice  = new AdminNotice( 'secret', 'Admins only' );
		$service->add_notice( $notice );

		self::assertSame( '', $this->capture_render( $service ) );
		// A skipped notice is left in the queue, not consumed.
		self::assertSame( array( 'secret' => $notice ), $service->notices );
	}

	private function administrator_id(): int {
		$admins = \get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => 'ID',
			),
		);
		self::assertNotEmpty( $admins );
		return (int) $admins[0];
	}

	private function capture_render( AdminNoticesService $service ): string {
		\ob_start();
		$service->render_notices();
		return (string) \ob_get_clean();
	}
}
