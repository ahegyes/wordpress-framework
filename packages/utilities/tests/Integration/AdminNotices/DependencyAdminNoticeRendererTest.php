<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Integration\AdminNotices;

use DeepWebSolutions\Framework\Core\Conditional\ConditionalInterface;
use DeepWebSolutions\Framework\Utilities\AdminNotices\AdminNoticesService;
use DeepWebSolutions\Framework\Utilities\AdminNotices\DependencyAdminNoticeRenderer;
use DeepWebSolutions\Framework\Utilities\AdminNotices\DismissedNoticesTracker;
use DeepWebSolutions\Framework\Utilities\AdminNotices\NoticeStore;
use DeepWebSolutions\Framework\Utilities\AdminNotices\ValueObjects\AdminNotice;
use DeepWebSolutions\Framework\Utilities\AdminNotices\ValueObjects\DependencyRequirement;
use DeepWebSolutions\Framework\Utilities\AdminNotices\NoticeType;
use DeepWebSolutions\Framework\Utilities\Conditionals\Dependencies\WPPluginActiveConditional;
use DeepWebSolutions\Framework\Storage\MemoryStore;
use DeepWebSolutions\Framework\Storage\OptionsStore;
use DeepWebSolutions\Framework\Storage\UserMetaStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( DependencyAdminNoticeRenderer::class )]
#[UsesClass( AdminNoticesService::class )]
#[UsesClass( DismissedNoticesTracker::class )]
#[UsesClass( NoticeStore::class )]
#[UsesClass( AdminNotice::class )]
#[UsesClass( DependencyRequirement::class )]
#[UsesClass( NoticeType::class )]
#[UsesClass( MemoryStore::class )]
#[UsesClass( OptionsStore::class )]
#[UsesClass( UserMetaStore::class )]
#[UsesClass( WPPluginActiveConditional::class )]
final class DependencyAdminNoticeRendererTest extends TestCase {
	private const DISMISS_KEY    = 'dws_test_dep_dismissed';
	private const DISMISS_ACTION = 'dws_test_dep_dismiss';
	private const PERSIST_KEY    = 'dws_test_dep_persistent';

	private int $admin;
	private int $subscriber;
	private int $original_user;

	protected function setUp(): void {
		parent::setUp();
		$this->original_user = \get_current_user_id();
		$this->admin         = $this->make_user( 'dws_dep_admin', 'administrator' );
		$this->subscriber    = $this->make_user( 'dws_dep_sub', 'subscriber' );
		\wp_set_current_user( $this->admin );
	}

	protected function tearDown(): void {
		\wp_set_current_user( $this->original_user );
		\delete_option( self::PERSIST_KEY );
		$this->delete_user( $this->admin );
		$this->delete_user( $this->subscriber );
		parent::tearDown();
	}

	public function test_met_dependency_queues_nothing(): void {
		$service = new AdminNoticesService();
		( new DependencyAdminNoticeRenderer(
			$service,
			array( new DependencyRequirement( $this->conditional( true ), 'WooCommerce' ) ),
		) )->render();

		self::assertSame( array(), $service->stores['memory']->get_all() );
	}

	public function test_unmet_required_dependency_queues_a_blocking_error(): void {
		$service = new AdminNoticesService();
		( new DependencyAdminNoticeRenderer(
			$service,
			array( new DependencyRequirement( $this->conditional( false ), 'WooCommerce' ) ),
			source: 'Linked Orders',
		) )->render();

		$notice = $service->stores['memory']->get( 'dep_woocommerce' );

		self::assertInstanceOf( AdminNotice::class, $notice );
		self::assertSame( NoticeType::Error, $notice->type );
		self::assertFalse( $notice->is_dismissible );
		self::assertFalse( $notice->is_persistent );
		self::assertSame( 'activate_plugins', $notice->capability );
		self::assertSame( 'Linked Orders requires WooCommerce to be active.', $notice->message );
	}

	public function test_unmet_optional_dependency_queues_a_dismissible_warning(): void {
		$service = new AdminNoticesService();
		( new DependencyAdminNoticeRenderer(
			$service,
			array( new DependencyRequirement( $this->conditional( false ), 'Jetpack', required: false ) ),
			source: 'Linked Orders',
		) )->render();

		$notice = $service->stores['memory']->get( 'dep_jetpack' );

		self::assertInstanceOf( AdminNotice::class, $notice );
		self::assertSame( NoticeType::Warning, $notice->type );
		self::assertTrue( $notice->is_dismissible );
		self::assertTrue( $notice->is_persistent );
		self::assertSame( 'Jetpack is recommended for Linked Orders.', $notice->message );
	}

	public function test_message_uses_a_generic_subject_without_a_source(): void {
		$service = new AdminNoticesService();
		( new DependencyAdminNoticeRenderer(
			$service,
			array( new DependencyRequirement( $this->conditional( false ), 'WooCommerce' ) ),
		) )->render();

		$notice = $service->stores['memory']->get( 'dep_woocommerce' );

		self::assertInstanceOf( AdminNotice::class, $notice );
		self::assertSame( 'This plugin requires WooCommerce to be active.', $notice->message );
	}

	public function test_optional_dependency_message_drops_the_subject_without_a_source(): void {
		$service = new AdminNoticesService();
		( new DependencyAdminNoticeRenderer(
			$service,
			array( new DependencyRequirement( $this->conditional( false ), 'Jetpack', required: false ) ),
		) )->render();

		$notice = $service->stores['memory']->get( 'dep_jetpack' );

		self::assertInstanceOf( AdminNotice::class, $notice );
		self::assertSame( 'Jetpack is recommended.', $notice->message );
	}

	public function test_only_unmet_dependencies_are_queued(): void {
		$service = new AdminNoticesService();
		( new DependencyAdminNoticeRenderer(
			$service,
			array(
				new DependencyRequirement( $this->conditional( true ), 'Met Plugin' ),
				new DependencyRequirement( $this->conditional( false ), 'Missing Plugin' ),
				new DependencyRequirement( $this->conditional( false ), 'Optional Plugin', required: false ),
			),
		) )->render();

		$all = $service->stores['memory']->get_all();

		self::assertArrayNotHasKey( 'dep_met_plugin', $all );
		self::assertArrayHasKey( 'dep_missing_plugin', $all );
		self::assertArrayHasKey( 'dep_optional_plugin', $all );
	}

	public function test_a_throwing_conditional_is_treated_as_unmet(): void {
		$service = new AdminNoticesService();
		( new DependencyAdminNoticeRenderer(
			$service,
			array( new DependencyRequirement( $this->throwing_conditional(), 'Flaky Dependency' ) ),
		) )->render();

		self::assertTrue( $service->stores['memory']->has( 'dep_flaky_dependency' ) );
	}

	public function test_an_unknown_store_triggers_doing_it_wrong(): void {
		$fired = 0;
		$spy   = static function () use ( &$fired ) {
			++$fired;
		};
		\add_filter( 'doing_it_wrong_trigger_error', '__return_false' );
		\add_action( 'doing_it_wrong_run', $spy );

		try {
			$service = new AdminNoticesService();
			( new DependencyAdminNoticeRenderer(
				$service,
				array( new DependencyRequirement( $this->conditional( false ), 'WooCommerce' ) ),
				store: 'nope',
			) )->render();

			self::assertGreaterThan( 0, $fired );
			self::assertSame( array(), $service->stores['memory']->get_all() );
		} finally {
			\remove_action( 'doing_it_wrong_run', $spy );
			\remove_filter( 'doing_it_wrong_trigger_error', '__return_false' );
		}
	}

	public function test_renders_for_a_capable_user_and_hides_from_others(): void {
		$requirement = static fn() => new DependencyRequirement( new WPPluginActiveConditional( 'does-not-exist/x.php' ), 'WooCommerce' );

		$admin_service = new AdminNoticesService();
		( new DependencyAdminNoticeRenderer( $admin_service, array( $requirement() ), source: 'Linked Orders' ) )->render();
		self::assertStringContainsString( 'WooCommerce', $this->capture_render( $admin_service ) );

		\wp_set_current_user( $this->subscriber );
		$sub_service = new AdminNoticesService();
		( new DependencyAdminNoticeRenderer( $sub_service, array( $requirement() ) ) )->render();
		self::assertSame( '', $this->capture_render( $sub_service ) );
	}

	public function test_optional_dependency_dismissal_is_sticky(): void {
		$tracker      = new DismissedNoticesTracker( new UserMetaStore( self::DISMISS_KEY ) );
		$requirements = array( new DependencyRequirement( $this->conditional( false ), 'Jetpack', required: false ) );

		$first = new AdminNoticesService( null, $tracker, self::DISMISS_ACTION );
		( new DependencyAdminNoticeRenderer( $first, $requirements ) )->render();
		self::assertStringContainsString( 'Jetpack', $this->capture_render( $first ) );

		$tracker->dismiss( 'dep_jetpack' );

		$second = new AdminNoticesService( null, $tracker, self::DISMISS_ACTION );
		( new DependencyAdminNoticeRenderer( $second, $requirements ) )->render();
		self::assertSame( '', $this->capture_render( $second ) );
	}

	public function test_a_now_met_dependency_stops_being_queued(): void {
		// Memory is per-request, so a dependency that becomes satisfied simply stops being re-queued.
		$first = new AdminNoticesService();
		( new DependencyAdminNoticeRenderer( $first, array( new DependencyRequirement( $this->conditional( false ), 'WooCommerce' ) ) ) )->render();
		self::assertTrue( $first->stores['memory']->has( 'dep_woocommerce' ) );

		$second = new AdminNoticesService();
		( new DependencyAdminNoticeRenderer( $second, array( new DependencyRequirement( $this->conditional( true ), 'WooCommerce' ) ) ) )->render();
		self::assertFalse( $second->stores['memory']->has( 'dep_woocommerce' ) );
	}

	public function test_a_now_met_dependency_is_cleared_from_a_persistent_store(): void {
		$service      = fn() => new AdminNoticesService( array( 'options' => new NoticeStore( new OptionsStore( self::PERSIST_KEY ) ) ) );
		$requirements = fn( bool $met ) => array( new DependencyRequirement( $this->conditional( $met ), 'Jetpack', required: false ) );

		// Unmet: the optional (persistent) notice lands in the cross-request options store.
		( new DependencyAdminNoticeRenderer( $service(), $requirements( false ), store: 'options' ) )->render();
		self::assertTrue( $service()->stores['options']->has( 'dep_jetpack' ) );

		// Now met: the renderer clears the stale persistent notice instead of leaving it to recur.
		( new DependencyAdminNoticeRenderer( $service(), $requirements( true ), store: 'options' ) )->render();
		self::assertFalse( $service()->stores['options']->has( 'dep_jetpack' ) );
	}

	private function conditional( bool $met ): ConditionalInterface {
		return new class( $met ) implements ConditionalInterface {
			public function __construct( private bool $met ) {}

			public function is_met(): bool {
				return $this->met;
			}
		};
	}

	private function throwing_conditional(): ConditionalInterface {
		return new class() implements ConditionalInterface {
			public function is_met(): bool {
				throw new \RuntimeException( 'dependency probe failed' );
			}
		};
	}

	private function capture_render( AdminNoticesService $service ): string {
		\ob_start();
		$service->render_notices();
		return (string) \ob_get_clean();
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
