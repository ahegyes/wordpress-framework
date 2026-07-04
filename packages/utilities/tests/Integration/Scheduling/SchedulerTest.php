<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Integration\Scheduling;

use DeepWebSolutions\Framework\Shared\Result\Success;
use DeepWebSolutions\Framework\Utilities\Scheduling\Backends\ActionSchedulerBackend;
use DeepWebSolutions\Framework\Utilities\Scheduling\Backends\WPCronBackend;
use DeepWebSolutions\Framework\Utilities\Scheduling\Scheduler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;


#[CoversClass( Scheduler::class )]
#[UsesClass( ActionSchedulerBackend::class )]
#[UsesClass( WPCronBackend::class )]
#[UsesClass( Success::class )]
final class SchedulerTest extends TestCase {
	private const HOOK = 'dws_test_scheduler_hook';

	private ?WPCronBackend $recurring_backend = null;

	protected function setUp(): void {
		parent::setUp();
		$this->clear_hook();
	}

	protected function tearDown(): void {
		if ( null !== $this->recurring_backend ) {
			\remove_filter( 'cron_schedules', array( $this->recurring_backend, 'register_synthetic_schedules' ) );
			$this->recurring_backend = null;
		}
		$this->clear_hook();
		parent::tearDown();
	}

	public function test_explicit_action_scheduler_wiring_routes_to_action_scheduler_when_initialized(): void {
		$this->require_action_scheduler();
		$scheduler = new Scheduler( array( new ActionSchedulerBackend(), new WPCronBackend() ) );
		$timestamp = \time() + 3600;

		self::assertInstanceOf( Success::class, $scheduler->schedule_single( self::HOOK, $timestamp ) );

		// The default probe reports Action Scheduler ready, so the action lands in Action Scheduler, not WordPress cron.
		self::assertIsInt( \as_next_scheduled_action( self::HOOK ) );
		self::assertFalse( \wp_next_scheduled( self::HOOK ) );
	}

	public function test_explicit_wiring_falls_back_to_wp_cron_when_action_scheduler_not_ready(): void {
		$scheduler = new Scheduler( array( new ActionSchedulerBackend( null, static fn (): bool => false ), new WPCronBackend() ) );
		$timestamp = \time() + 3600;

		self::assertInstanceOf( Success::class, $scheduler->schedule_single( self::HOOK, $timestamp ) );

		// The probe reports Action Scheduler not ready, so the event falls back to WordPress cron, not Action Scheduler.
		self::assertSame( $timestamp, \wp_next_scheduled( self::HOOK ) );
		if ( \function_exists( 'as_next_scheduled_action' ) ) {
			self::assertFalse( \as_next_scheduled_action( self::HOOK ) );
		}
	}

	public function test_unschedule_routes_through_the_ready_backend(): void {
		$this->require_action_scheduler();
		$scheduler = new Scheduler( array( new ActionSchedulerBackend(), new WPCronBackend() ) );
		self::assertInstanceOf( Success::class, $scheduler->schedule_single( self::HOOK, \time() + 3600 ) );
		self::assertTrue( $scheduler->is_scheduled( self::HOOK ) );

		self::assertInstanceOf( Success::class, $scheduler->unschedule( self::HOOK ) );

		self::assertFalse( $scheduler->is_scheduled( self::HOOK ) );
		self::assertFalse( \as_next_scheduled_action( self::HOOK ) );
	}

	public function test_unschedule_clears_the_wp_cron_backend_for_a_job_scheduled_before_action_scheduler_was_ready(): void {
		$this->require_action_scheduler();
		$timestamp = \time() + 3600;

		// Model the migration scenario: the job lands on WordPress cron while Action Scheduler reports not ready.
		$wp_cron_scheduler = new Scheduler( array( new ActionSchedulerBackend( null, static fn (): bool => false ), new WPCronBackend() ) );
		self::assertInstanceOf( Success::class, $wp_cron_scheduler->schedule_single( self::HOOK, $timestamp ) );
		self::assertSame( $timestamp, \wp_next_scheduled( self::HOOK ) );

		// Action Scheduler is ready now, but unschedule must still reach the WordPress cron backend and clear it.
		self::assertInstanceOf( Success::class, new Scheduler( array( new ActionSchedulerBackend( null, static fn (): bool => true ), new WPCronBackend() ) )->unschedule( self::HOOK ) );

		self::assertFalse( \wp_next_scheduled( self::HOOK ) );
	}

	public function test_unschedule_clears_a_wp_cron_job_while_action_scheduler_is_not_ready(): void {
		$timestamp = \time() + 3600;
		$scheduler = new Scheduler( array( new ActionSchedulerBackend( null, static fn (): bool => false ), new WPCronBackend() ) );
		self::assertInstanceOf( Success::class, $scheduler->schedule_single( self::HOOK, $timestamp ) );
		self::assertSame( $timestamp, \wp_next_scheduled( self::HOOK ) );

		// The probe reports Action Scheduler not ready, so the clear succeeds on WordPress cron alone.
		self::assertInstanceOf( Success::class, $scheduler->unschedule( self::HOOK ) );

		self::assertFalse( \wp_next_scheduled( self::HOOK ) );
	}

	public function test_get_next_scheduled_returns_the_wp_cron_timestamp_when_only_wp_cron_holds_the_event(): void {
		$timestamp = \time() + 3600;
		$scheduler = new Scheduler( array( new ActionSchedulerBackend( null, static fn (): bool => false ), new WPCronBackend() ) );
		self::assertInstanceOf( Success::class, $scheduler->schedule_single( self::HOOK, $timestamp ) );

		// Action Scheduler holds nothing for this hook, so the facade returns the WordPress cron timestamp.
		self::assertSame( $timestamp, $scheduler->get_next_scheduled( self::HOOK ) );
	}

	public function test_get_next_scheduled_returns_the_earliest_timestamp_across_both_backends(): void {
		$this->require_action_scheduler();
		$wp_cron_timestamp          = \time() + 1800;
		$action_scheduler_timestamp = \time() + 3600;

		self::assertInstanceOf( Success::class, new Scheduler( array( new ActionSchedulerBackend( null, static fn (): bool => false ), new WPCronBackend() ) )->schedule_single( self::HOOK, $wp_cron_timestamp ) );
		self::assertInstanceOf( Success::class, new Scheduler( array( new ActionSchedulerBackend( null, static fn (): bool => true ), new WPCronBackend() ) )->schedule_single( self::HOOK, $action_scheduler_timestamp ) );

		// Both backends now hold a job; the facade returns the earliest run across them, not whichever it reads first.
		self::assertSame( $wp_cron_timestamp, new Scheduler( array( new ActionSchedulerBackend(), new WPCronBackend() ) )->get_next_scheduled( self::HOOK ) );
	}

	public function test_register_lifecycle_through_the_facade_reconstructs_a_wp_cron_recurrence(): void {
		// Schedule a recurring event straight on a WordPress cron backend, then drop that backend's own
		// filter to model a later request where only the lifecycle is wired. The interval is unique to
		// this test so no other registered 'cron_schedules' callback can resolve the synthetic schedule.
		$backend = new WPCronBackend();
		self::assertInstanceOf( Success::class, $backend->schedule_recurring( self::HOOK, 271 ) );
		\remove_filter( 'cron_schedules', array( $backend, 'register_synthetic_schedules' ) );
		self::assertArrayNotHasKey( 'dws_every_271s', \wp_get_schedules() );

		new Scheduler( array( new ActionSchedulerBackend(), new WPCronBackend() ) )->register_lifecycle();

		// The facade fans register_lifecycle out to its WordPress cron backend, which rebuilds the synthetic
		// schedule from the cron array so WordPress can reschedule the recurring event.
		self::assertArrayHasKey( 'dws_every_271s', \wp_get_schedules() );
	}

	public function test_schedule_recurring_through_the_facade_routes_to_wp_cron_when_action_scheduler_not_ready(): void {
		// Hold the WordPress cron backend directly so tearDown can drop its 'cron_schedules' filter; the
		// interval is unique to this test so no other schedule resolves the synthetic schedule asserted below.
		$this->recurring_backend = new WPCronBackend();
		$scheduler               = new Scheduler( array( new ActionSchedulerBackend( null, static fn (): bool => false ), $this->recurring_backend ) );

		self::assertInstanceOf( Success::class, $scheduler->schedule_recurring( self::HOOK, 263 ) );

		// The probe reports Action Scheduler not ready, so schedule_recurring routes to WordPress cron: the
		// synthetic schedule resolves and the recurring event is queued.
		self::assertArrayHasKey( 'dws_every_263s', \wp_get_schedules() );
		self::assertIsInt( \wp_next_scheduled( self::HOOK ) );
	}

	private function require_action_scheduler(): void {
		if ( ! \function_exists( 'as_schedule_recurring_action' ) || 0 === \did_action( 'action_scheduler_init' ) ) {
			self::markTestSkipped( 'Action Scheduler is not ready in this environment.' );
		}
	}

	private function clear_hook(): void {
		\wp_unschedule_hook( self::HOOK );
		if ( \function_exists( 'as_get_scheduled_actions' ) ) {
			foreach ( \as_get_scheduled_actions(
				array(
					'hook'     => self::HOOK,
					'per_page' => -1,
				),
				'ids'
			) as $id ) {
				\ActionScheduler_Store::instance()->delete_action( (string) $id );
			}
		}
	}
}
