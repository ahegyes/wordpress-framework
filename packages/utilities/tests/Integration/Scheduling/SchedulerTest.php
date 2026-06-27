<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Integration\Scheduling;

use DeepWebSolutions\Framework\Shared\Result\Success;
use DeepWebSolutions\Framework\Utilities\Scheduling\Backends\ActionSchedulerBackend;
use DeepWebSolutions\Framework\Utilities\Scheduling\Backends\WPCronBackend;
use DeepWebSolutions\Framework\Utilities\Scheduling\Scheduler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function DeepWebSolutions\Framework\Utilities\Scheduling\create_scheduler;

#[CoversClass( Scheduler::class )]
#[CoversFunction( 'DeepWebSolutions\Framework\Utilities\Scheduling\create_scheduler' )]
#[UsesClass( ActionSchedulerBackend::class )]
#[UsesClass( WPCronBackend::class )]
#[UsesClass( Success::class )]
final class SchedulerTest extends TestCase {
	private const HOOK = 'dws_test_scheduler_hook';

	protected function setUp(): void {
		parent::setUp();
		$this->clear_hook();
	}

	protected function tearDown(): void {
		$this->clear_hook();
		parent::tearDown();
	}

	public function test_create_scheduler_routes_to_action_scheduler_when_initialized(): void {
		$this->require_action_scheduler();
		$scheduler = create_scheduler();
		$timestamp = \time() + 3600;

		self::assertInstanceOf( Success::class, $scheduler->schedule_single( self::HOOK, $timestamp ) );

		// The default probe reports Action Scheduler ready, so the action lands in Action Scheduler, not WordPress cron.
		self::assertIsInt( \as_next_scheduled_action( self::HOOK ) );
		self::assertFalse( \wp_next_scheduled( self::HOOK ) );
	}

	public function test_create_scheduler_falls_back_to_wp_cron_when_action_scheduler_not_ready(): void {
		$scheduler = create_scheduler( null, static fn (): bool => false );
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
		$scheduler = create_scheduler();
		self::assertInstanceOf( Success::class, $scheduler->schedule_single( self::HOOK, \time() + 3600 ) );
		self::assertTrue( $scheduler->is_scheduled( self::HOOK ) );

		self::assertInstanceOf( Success::class, $scheduler->unschedule( self::HOOK ) );

		self::assertFalse( $scheduler->is_scheduled( self::HOOK ) );
		self::assertFalse( \as_next_scheduled_action( self::HOOK ) );
	}

	public function test_register_lifecycle_through_the_facade_reconstructs_a_wp_cron_recurrence(): void {
		// Schedule a recurring event straight on a WordPress cron backend, then drop that backend's own
		// filter to model a later request where only the lifecycle is wired. The interval is unique to
		// this test so no other registered 'cron_schedules' callback can resolve the synthetic schedule.
		$backend = new WPCronBackend();
		self::assertInstanceOf( Success::class, $backend->schedule_recurring( self::HOOK, 271 ) );
		\remove_filter( 'cron_schedules', array( $backend, 'register_synthetic_schedules' ) );
		self::assertArrayNotHasKey( 'dws_every_271s', \wp_get_schedules() );

		create_scheduler()->register_lifecycle();

		// The facade fans register_lifecycle out to its WordPress cron backend, which rebuilds the synthetic
		// schedule from the cron array so WordPress can reschedule the recurring event.
		self::assertArrayHasKey( 'dws_every_271s', \wp_get_schedules() );
	}

	private function require_action_scheduler(): void {
		if ( ! \function_exists( 'as_schedule_recurring_action' ) || 0 === \did_action( 'action_scheduler_init' ) ) {
			self::markTestSkipped( 'Action Scheduler is not ready in this environment.' );
		}
	}

	private function clear_hook(): void {
		\wp_unschedule_hook( self::HOOK );
		if ( \function_exists( 'as_get_scheduled_actions' ) ) {
			foreach ( \as_get_scheduled_actions( array( 'hook' => self::HOOK, 'per_page' => -1 ), 'ids' ) as $id ) {
				\ActionScheduler_Store::instance()->delete_action( (string) $id );
			}
		}
	}
}
