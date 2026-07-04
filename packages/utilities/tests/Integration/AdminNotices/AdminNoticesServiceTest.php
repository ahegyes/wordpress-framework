<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Integration\AdminNotices;

use DeepWebSolutions\Framework\Utilities\AdminNotices\AdminNoticesService;
use DeepWebSolutions\Framework\Utilities\AdminNotices\DismissedNoticesTracker;
use DeepWebSolutions\Framework\Utilities\AdminNotices\NoticeStore;
use DeepWebSolutions\Framework\Utilities\AdminNotices\ValueObjects\AdminNotice;
use DeepWebSolutions\Framework\Utilities\AdminNotices\NoticeType;
use DeepWebSolutions\Framework\Storage\MemoryStore;
use DeepWebSolutions\Framework\Storage\OptionsStore;
use DeepWebSolutions\Framework\Storage\UserMetaStore;
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
	private const NOTICE_KEY     = 'dws_test_service_notices';
	private const DISMISS_KEY    = 'dws_test_service_dismissed';
	private const DISMISS_ACTION = 'dws_test_dismiss_notice';

	private int $admin_a;
	private int $admin_b;
	private int $subscriber;
	private int $original_user;

	protected function setUp(): void {
		parent::setUp();
		$this->original_user = \get_current_user_id();
		$this->admin_a       = $this->make_admin( 'dws_notice_admin_a' );
		$this->admin_b       = $this->make_admin( 'dws_notice_admin_b' );
		$this->subscriber    = $this->make_user( 'dws_notice_subscriber', 'subscriber' );
		\wp_set_current_user( $this->admin_a );
	}

	protected function tearDown(): void {
		unset( $_POST['id'], $_REQUEST['_wpnonce'], $_REQUEST['_ajax_nonce'] );
		\wp_set_current_user( $this->original_user );
		\delete_option( self::NOTICE_KEY );
		$this->delete_user( $this->admin_a );
		$this->delete_user( $this->admin_b );
		$this->delete_user( $this->subscriber );
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

	public function test_render_emits_notice_id_and_scopes_dismiss_action_when_configured(): void {
		$with = $this->transport_service();
		$with->add_notice(
			new AdminNotice( 'setup', 'Configure me.', NoticeType::Warning, is_persistent: true ),
			'user-meta',
		);
		$with_output = $this->capture_render( $with );

		self::assertStringContainsString( 'data-notice-id="setup"', $with_output );
		self::assertStringContainsString( 'data-dismiss-action="' . self::DISMISS_ACTION . '"', $with_output );

		$without = new AdminNoticesService();
		$without->add_notice( new AdminNotice( 'plain', 'No transport here.' ) );
		$without_output = $this->capture_render( $without );

		self::assertStringContainsString( 'data-notice-id="plain"', $without_output );
		self::assertStringNotContainsString( 'data-dismiss-action', $without_output );
	}

	public function test_print_dismiss_script_emits_a_scoped_listener_with_action_and_nonce(): void {
		\ob_start();
		$this->transport_service()->print_dismiss_script();
		$output = (string) \ob_get_clean();

		self::assertStringContainsString( '<script', $output );
		self::assertStringContainsString( self::DISMISS_ACTION, $output );
		self::assertStringContainsString( 'data-dismiss-action', $output );
		self::assertStringContainsString( 'data-notice-id', $output );
		self::assertStringContainsString( '_wpnonce', $output );
		self::assertStringContainsString( '"POST"', $output );
		self::assertStringContainsString( 'same-origin', $output );
	}

	public function test_two_services_print_independently_scoped_scripts(): void {
		$alpha = new AdminNoticesService( dismissals: $this->tracker(), dismiss_action: 'dws_alpha_dismiss' );
		$beta  = new AdminNoticesService( dismissals: $this->tracker(), dismiss_action: 'dws_beta_dismiss' );

		\ob_start();
		$alpha->print_dismiss_script();
		$alpha_output = (string) \ob_get_clean();

		\ob_start();
		$beta->print_dismiss_script();
		$beta_output = (string) \ob_get_clean();

		self::assertStringContainsString( 'dws_alpha_dismiss', $alpha_output );
		self::assertStringNotContainsString( 'dws_beta_dismiss', $alpha_output );
		self::assertStringContainsString( 'dws_beta_dismiss', $beta_output );
		self::assertStringNotContainsString( 'dws_alpha_dismiss', $beta_output );
	}

	public function test_register_hooks_wires_render_footer_and_ajax_callbacks(): void {
		$service = $this->transport_service();

		try {
			$service->register_hooks();

			self::assertSame( 10, \has_action( 'admin_notices', array( $service, 'render_notices' ) ) );
			self::assertSame( 10, \has_action( 'admin_footer', array( $service, 'print_dismiss_script' ) ) );
			self::assertSame( 10, \has_action( 'wp_ajax_' . self::DISMISS_ACTION, array( $service, 'handle_dismiss' ) ) );
		} finally {
			\remove_action( 'admin_notices', array( $service, 'render_notices' ) );
			\remove_action( 'admin_footer', array( $service, 'print_dismiss_script' ) );
			\remove_action( 'wp_ajax_' . self::DISMISS_ACTION, array( $service, 'handle_dismiss' ) );
		}
	}

	public function test_register_hooks_without_a_dismiss_action_wires_no_ajax_endpoint(): void {
		$service = new AdminNoticesService();

		try {
			$service->register_hooks();

			self::assertSame( 10, \has_action( 'admin_notices', array( $service, 'render_notices' ) ) );
			self::assertSame( 10, \has_action( 'admin_footer', array( $service, 'print_dismiss_script' ) ) );
			self::assertFalse( \has_action( 'wp_ajax_', array( $service, 'handle_dismiss' ) ) );
		} finally {
			\remove_action( 'admin_notices', array( $service, 'render_notices' ) );
			\remove_action( 'admin_footer', array( $service, 'print_dismiss_script' ) );
		}
	}

	public function test_handle_dismiss_records_dismissal_with_a_valid_nonce(): void {
		$this->transport_service()->add_notice(
			new AdminNotice( 'dep_woocommerce', 'WooCommerce is required.', NoticeType::Error, is_persistent: true ),
			'user-meta',
		);
		$_REQUEST['_wpnonce'] = \wp_create_nonce( self::DISMISS_ACTION );
		$_POST['id']          = 'dep_woocommerce';

		$this->run_until_wp_die( fn() => $this->transport_service()->handle_dismiss() );

		self::assertTrue( $this->tracker()->is_dismissed( 'dep_woocommerce' ) );
	}

	public function test_handle_dismiss_ignores_an_invalid_nonce(): void {
		$_REQUEST['_wpnonce'] = 'not-a-valid-nonce';
		$_POST['id']          = 'dep_woocommerce';

		$this->run_until_wp_die( fn() => $this->transport_service()->handle_dismiss() );

		self::assertFalse( $this->tracker()->is_dismissed( 'dep_woocommerce' ) );
	}

	public function test_handle_dismiss_ignores_a_logged_out_user(): void {
		\wp_set_current_user( 0 );
		$_REQUEST['_wpnonce'] = \wp_create_nonce( self::DISMISS_ACTION );
		$_POST['id']          = 'dep_woocommerce';

		$this->run_until_wp_die( fn() => $this->transport_service()->handle_dismiss() );

		\wp_set_current_user( $this->admin_a );
		self::assertFalse( $this->tracker()->is_dismissed( 'dep_woocommerce' ) );
	}

	public function test_handle_dismiss_with_a_missing_id_records_nothing_and_does_not_warn(): void {
		$_REQUEST['_wpnonce'] = \wp_create_nonce( self::DISMISS_ACTION );

		// No $_POST['id']: the ?? '' guard avoids an undefined-index warning (the suite fails on warnings).
		$this->run_until_wp_die( fn() => $this->transport_service()->handle_dismiss() );

		self::assertFalse( $this->tracker()->is_dismissed( 'dep_woocommerce' ) );
	}

	public function test_handle_dismiss_ignores_a_well_formed_but_never_rendered_id(): void {
		$_REQUEST['_wpnonce'] = \wp_create_nonce( self::DISMISS_ACTION );
		$_POST['id']          = 'never_rendered';

		$this->run_until_wp_die( fn() => $this->transport_service()->handle_dismiss() );

		self::assertFalse( $this->tracker()->is_dismissed( 'never_rendered' ) );
	}

	public function test_handle_dismiss_ignores_a_known_non_persistent_notice(): void {
		$this->transport_service()->add_notice(
			new AdminNotice( 'flash_notice', 'Saved.', NoticeType::Success, is_persistent: false ),
			'user-meta',
		);
		$_REQUEST['_wpnonce'] = \wp_create_nonce( self::DISMISS_ACTION );
		$_POST['id']          = 'flash_notice';

		$this->run_until_wp_die( fn() => $this->transport_service()->handle_dismiss() );

		self::assertFalse( $this->tracker()->is_dismissed( 'flash_notice' ) );
	}

	public function test_handle_dismiss_ignores_a_known_non_dismissible_notice(): void {
		$this->transport_service()->add_notice(
			new AdminNotice( 'fixed_notice', 'Fixed.', NoticeType::Info, is_dismissible: false, is_persistent: true ),
			'user-meta',
		);
		$_REQUEST['_wpnonce'] = \wp_create_nonce( self::DISMISS_ACTION );
		$_POST['id']          = 'fixed_notice';

		$this->run_until_wp_die( fn() => $this->transport_service()->handle_dismiss() );

		self::assertFalse( $this->tracker()->is_dismissed( 'fixed_notice' ) );
	}

	public function test_handle_dismiss_ignores_a_known_notice_the_current_user_cannot_see(): void {
		$this->options_transport_service()->add_notice(
			new AdminNotice( 'admin_only', 'Admins only.', NoticeType::Warning, is_persistent: true, capability: 'manage_options' ),
			'options',
		);

		\wp_set_current_user( $this->subscriber );
		$_REQUEST['_wpnonce'] = \wp_create_nonce( self::DISMISS_ACTION );
		$_POST['id']          = 'admin_only';

		$this->run_until_wp_die( fn() => $this->options_transport_service()->handle_dismiss() );

		self::assertFalse( $this->tracker()->is_dismissed( 'admin_only' ) );
	}

	public function test_handle_dismiss_without_a_transport_records_nothing(): void {
		$_REQUEST['_wpnonce'] = \wp_create_nonce( self::DISMISS_ACTION );
		$_POST['id']          = 'dep_woocommerce';

		$this->run_until_wp_die( fn() => ( new AdminNoticesService() )->handle_dismiss() );

		self::assertFalse( $this->tracker()->is_dismissed( 'dep_woocommerce' ) );
	}

	public function test_endpoint_dismissal_suppresses_the_notice_on_the_next_render(): void {
		$this->transport_service()->add_notice(
			new AdminNotice( 'dep_wc', 'WooCommerce is required.', NoticeType::Error, is_persistent: true ),
			'user-meta',
		);

		$first = $this->capture_render( $this->transport_service() );
		self::assertStringContainsString( 'WooCommerce is required.', $first );
		self::assertStringContainsString( 'data-notice-id="dep_wc"', $first );

		$_REQUEST['_wpnonce'] = \wp_create_nonce( self::DISMISS_ACTION );
		$_POST['id']          = 'dep_wc';
		$this->run_until_wp_die( fn() => $this->transport_service()->handle_dismiss() );

		self::assertSame( '', $this->capture_render( $this->transport_service() ) );
		// Suppression is not removal — the persistent row survives.
		self::assertTrue( $this->transport_service()->stores['user-meta']->has( 'dep_wc' ) );
	}

	public function test_render_scopes_dismiss_action_only_for_sticky_notices(): void {
		$service = $this->transport_service();
		$service->add_notice(
			new AdminNotice( 'sticky_dep', 'Sticky.', NoticeType::Warning, is_dismissible: true, is_persistent: true ),
			'user-meta',
		);
		$service->add_notice(
			new AdminNotice( 'flash_msg', 'Flash.', NoticeType::Info, is_dismissible: true, is_persistent: false ),
			'user-meta',
		);

		$output = $this->capture_render( $service );

		// The sticky notice carries the transport marker; the one-shot is never tracker-gated, so it must not.
		self::assertMatchesRegularExpression( '/data-notice-id="sticky_dep"[^>]*data-dismiss-action/', $output );
		self::assertStringContainsString( 'data-notice-id="flash_msg"', $output );
		self::assertDoesNotMatchRegularExpression( '/data-notice-id="flash_msg"[^>]*data-dismiss-action/', $output );
	}

	public function test_handle_dismiss_rejects_a_non_sanitize_key_stable_id(): void {
		$_REQUEST['_wpnonce'] = \wp_create_nonce( self::DISMISS_ACTION );
		$_POST['id']          = 'Mixed_Case';

		$this->run_until_wp_die( fn() => $this->transport_service()->handle_dismiss() );

		// Rejected without lossy normalization: neither the raw nor a lowercased key is recorded.
		self::assertFalse( $this->tracker()->is_dismissed( 'Mixed_Case' ) );
		self::assertFalse( $this->tracker()->is_dismissed( 'mixed_case' ) );
	}

	private function transport_service(): AdminNoticesService {
		return new AdminNoticesService(
			array( 'user-meta' => new NoticeStore( new UserMetaStore( self::NOTICE_KEY ) ) ),
			$this->tracker(),
			self::DISMISS_ACTION,
		);
	}

	// Runs a callable expected to terminate via wp_die(), trapping the exit so the test can assert side
	// effects; swaps the WP die handlers for a thrower for the duration of the call.
	private function run_until_wp_die( callable $fn ): void {
		$thrower = static fn() => static function (): void {
			throw new \RuntimeException( '__dws_wp_die__' );
		};
		\add_filter( 'wp_die_handler', $thrower );
		\add_filter( 'wp_die_ajax_handler', $thrower );

		try {
			$fn();
			self::fail( 'Expected wp_die() to terminate the request.' );
		} catch ( \RuntimeException $e ) {
			if ( '__dws_wp_die__' !== $e->getMessage() ) {
				throw $e;
			}
		} finally {
			\remove_filter( 'wp_die_handler', $thrower );
			\remove_filter( 'wp_die_ajax_handler', $thrower );
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

	private function options_transport_service(): AdminNoticesService {
		return new AdminNoticesService(
			array( 'options' => new NoticeStore( new OptionsStore( self::NOTICE_KEY ) ) ),
			$this->tracker(),
			self::DISMISS_ACTION,
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
		return $this->make_user( $login, 'administrator' );
	}

	private function make_user( string $login, string $role ): int {
		$existing = \get_user_by( 'login', $login );
		if ( $existing instanceof \WP_User ) {
			return $existing->ID;
		}

		$id = \wp_insert_user(
			array(
				'user_login' => $login,
				'user_pass'  => 'password',
				'role'       => $role,
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
