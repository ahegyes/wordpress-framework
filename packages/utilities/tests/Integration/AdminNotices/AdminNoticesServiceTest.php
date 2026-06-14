<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Integration\AdminNotices;

use DeepWebSolutions\Framework\Utilities\AdminNotices\AdminNoticesService;
use DeepWebSolutions\Framework\Utilities\AdminNotices\DismissedNoticesTracker;
use DeepWebSolutions\Framework\Utilities\AdminNotices\NoticeStore;
use DeepWebSolutions\Framework\Utilities\AdminNotices\ValueObjects\AdminNotice;
use DeepWebSolutions\Framework\Utilities\AdminNotices\ValueObjects\NoticeType;
use DeepWebSolutions\Framework\Utilities\Storage\MemoryStore;
use DeepWebSolutions\Framework\Utilities\Storage\OptionsStore;
use DeepWebSolutions\Framework\Utilities\Storage\UserMetaStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( AdminNoticesService::class )]
#[UsesClass( NoticeStore::class )]
#[UsesClass( DismissedNoticesTracker::class )]
#[UsesClass( AdminNotice::class )]
#[UsesClass( NoticeType::class )]
#[UsesClass( MemoryStore::class )]
#[UsesClass( OptionsStore::class )]
#[UsesClass( UserMetaStore::class )]
final class AdminNoticesServiceTest extends TestCase {
	private const NOTICE_KEY  = 'dws_test_service_notices';
	private const DISMISS_KEY = 'dws_test_service_dismissed';

	private int $admin_a;
	private int $admin_b;
	private int $original_user;

	protected function setUp(): void {
		parent::setUp();
		$this->original_user = \get_current_user_id();
		$this->admin_a       = $this->make_admin( 'dws_notice_admin_a' );
		$this->admin_b       = $this->make_admin( 'dws_notice_admin_b' );
		\wp_set_current_user( $this->admin_a );
	}

	protected function tearDown(): void {
		\wp_set_current_user( $this->original_user );
		\delete_option( self::NOTICE_KEY );
		$this->delete_user( $this->admin_a );
		$this->delete_user( $this->admin_b );
		parent::tearDown();
	}

	public function test_renders_notice_for_capable_user(): void {
		$service = new AdminNoticesService();
		$service->add_notice( new AdminNotice( 'welcome', 'Hello admin', NoticeType::Success ) );

		$output = $this->capture_render( $service );

		self::assertStringContainsString( 'Hello admin', $output );
		self::assertStringContainsString( 'dws-notice-welcome', $output );
		self::assertStringContainsString( 'notice-success', $output );
		self::assertStringContainsString( 'is-dismissible', $output );
	}

	public function test_skips_notice_for_user_without_capability_and_leaves_it_queued(): void {
		\wp_set_current_user( 0 );
		$service = new AdminNoticesService();
		$service->add_notice( new AdminNotice( 'secret', 'Admins only' ) );

		self::assertSame( '', $this->capture_render( $service ) );
		self::assertTrue( $service->stores['memory']->has( 'secret' ) );
	}

	public function test_non_persistent_notice_is_consumed_after_rendering_once(): void {
		$this->user_meta_service()->add_notice(
			new AdminNotice( 'flash', 'Saved.', NoticeType::Success, is_persistent: false ),
			'user-meta',
		);

		$first  = $this->user_meta_service();
		$output = $this->capture_render( $first );
		self::assertStringContainsString( 'Saved.', $output );
		self::assertFalse( $first->stores['user-meta']->has( 'flash' ) );

		// A fresh "request" sees nothing — the one-shot was consumed.
		self::assertSame( '', $this->capture_render( $this->user_meta_service() ) );
	}

	public function test_persistent_notice_recurs_until_dismissed(): void {
		$this->user_meta_service()->add_notice(
			new AdminNotice( 'setup', 'Finish setup.', NoticeType::Warning, is_persistent: true ),
			'user-meta',
		);

		self::assertStringContainsString( 'Finish setup.', $this->capture_render( $this->user_meta_service() ) );
		// Still there on the next request (persistent, not consumed).
		self::assertStringContainsString( 'Finish setup.', $this->capture_render( $this->user_meta_service() ) );

		$this->tracker()->dismiss( 'setup' );

		self::assertSame( '', $this->capture_render( $this->user_meta_service() ) );
		// Suppression is not removal — the row survives.
		self::assertTrue( $this->user_meta_service()->stores['user-meta']->has( 'setup' ) );
	}

	public function test_one_shot_id_reuse_is_not_suppressed_by_a_prior_dismissal(): void {
		$this->tracker()->dismiss( 'reused' );

		$service = $this->user_meta_service();
		$service->add_notice(
			new AdminNotice( 'reused', 'A brand-new error.', NoticeType::Error, is_persistent: false ),
			'user-meta',
		);

		// One-shots are never tracker-gated, so the reused ID still renders and is consumed.
		self::assertStringContainsString( 'A brand-new error.', $this->capture_render( $service ) );
		self::assertFalse( $service->stores['user-meta']->has( 'reused' ) );
	}

	public function test_site_wide_dismissal_is_per_user(): void {
		$service = new AdminNoticesService(
			array( 'options' => new NoticeStore( new OptionsStore( self::NOTICE_KEY ) ) ),
			$this->tracker(),
		);
		$service->add_notice(
			new AdminNotice( 'wc_missing', 'WooCommerce is required.', NoticeType::Error, is_persistent: true ),
			'options',
		);

		$this->tracker()->dismiss( 'wc_missing' );
		self::assertSame( '', $this->capture_render( $this->options_service() ) );

		\wp_set_current_user( $this->admin_b );
		self::assertStringContainsString( 'WooCommerce is required.', $this->capture_render( $this->options_service() ) );
		// The shared site-wide row still exists after A dismissed it.
		self::assertTrue( $this->options_service()->stores['options']->has( 'wc_missing' ) );
	}

	public function test_renders_notices_from_multiple_stores_in_one_pass(): void {
		$service = new AdminNoticesService(
			array(
				'memory'    => new NoticeStore( new MemoryStore() ),
				'user-meta' => new NoticeStore( new UserMetaStore( self::NOTICE_KEY ) ),
			),
		);
		$service->add_notice( new AdminNotice( 'mem', 'From memory', NoticeType::Info ), 'memory' );
		$service->add_notice( new AdminNotice( 'um', 'From user meta', NoticeType::Info ), 'user-meta' );

		$output = $this->capture_render( $service );

		self::assertStringContainsString( 'From memory', $output );
		self::assertStringContainsString( 'From user meta', $output );
		// Both are non-persistent, so each is consumed from its own store after rendering.
		self::assertFalse( $service->stores['memory']->has( 'mem' ) );
		self::assertFalse( $service->stores['user-meta']->has( 'um' ) );
	}

	public function test_site_wide_one_shot_is_consumed_by_the_first_viewer(): void {
		// Documented sharp edge: a non-persistent notice in the site-wide options store is consumed by
		// the first render (last-writer-wins under concurrency), so other users never see it — one-shots
		// belong in a per-user store. This test characterizes the single-request behavior.
		$service = $this->options_service();
		$service->add_notice(
			new AdminNotice( 'broadcast', 'Seen once, by whoever is first.', NoticeType::Info, is_persistent: false ),
			'options',
		);

		self::assertStringContainsString( 'Seen once', $this->capture_render( $this->options_service() ) );

		// The row is gone site-wide — a second viewer sees nothing.
		\wp_set_current_user( $this->admin_b );
		self::assertSame( '', $this->capture_render( $this->options_service() ) );
	}

	public function test_unknown_store_triggers_doing_it_wrong_and_stores_nothing(): void {
		$fired = 0;
		$spy   = static function () use ( &$fired ) {
			++$fired;
		};
		\add_filter( 'doing_it_wrong_trigger_error', '__return_false' );
		\add_action( 'doing_it_wrong_run', $spy );

		try {
			$service = new AdminNoticesService();
			$service->add_notice( new AdminNotice( 'x', 'msg' ), 'nope' );

			self::assertGreaterThan( 0, $fired );
			self::assertSame( '', $this->capture_render( $service ) );
		} finally {
			\remove_action( 'doing_it_wrong_run', $spy );
			\remove_filter( 'doing_it_wrong_trigger_error', '__return_false' );
		}
	}

	private function user_meta_service(): AdminNoticesService {
		return new AdminNoticesService(
			array( 'user-meta' => new NoticeStore( new UserMetaStore( self::NOTICE_KEY ) ) ),
			$this->tracker(),
		);
	}

	private function options_service(): AdminNoticesService {
		return new AdminNoticesService(
			array( 'options' => new NoticeStore( new OptionsStore( self::NOTICE_KEY ) ) ),
			$this->tracker(),
		);
	}

	private function tracker(): DismissedNoticesTracker {
		return new DismissedNoticesTracker( new UserMetaStore( self::DISMISS_KEY ) );
	}

	private function capture_render( AdminNoticesService $service ): string {
		\ob_start();
		$service->render_notices();
		return (string) \ob_get_clean();
	}

	private function make_admin( string $login ): int {
		$existing = \get_user_by( 'login', $login );
		if ( $existing instanceof \WP_User ) {
			return $existing->ID;
		}

		$id = \wp_insert_user(
			array(
				'user_login' => $login,
				'user_pass'  => 'password',
				'role'       => 'administrator',
			),
		);
		self::assertIsInt( $id );
		return $id;
	}

	private function delete_user( int $id ): void {
		if ( ! \function_exists( 'wp_delete_user' ) ) {
			require_once \ABSPATH . 'wp-admin/includes/user.php';
		}
		\wp_delete_user( $id );
	}
}
