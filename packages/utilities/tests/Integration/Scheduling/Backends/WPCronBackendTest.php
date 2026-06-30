<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Integration\Scheduling\Backends;

use DeepWebSolutions\Framework\Shared\Result\Failure;
use DeepWebSolutions\Framework\Shared\Result\Success;
use DeepWebSolutions\Framework\Utilities\Scheduling\Backends\WPCronBackend;
use DeepWebSolutions\Framework\Utilities\Scheduling\Errors\SchedulingError;
use DeepWebSolutions\Framework\Utilities\Scheduling\SchedulingErrorReason;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( WPCronBackend::class )]
#[UsesClass( SchedulingError::class )]
#[UsesClass( SchedulingErrorReason::class )]
#[UsesClass( Success::class )]
#[UsesClass( Failure::class )]
final class WPCronBackendTest extends TestCase {
	private const HOOK = 'dws_test_wpcron_hook';

	/**
	 * @var list<WPCronBackend>
	 */
	private array $backends = array();

	protected function setUp(): void {
		parent::setUp();
		// wp_unschedule_hook clears every event for the hook regardless of args; wp_clear_scheduled_hook
		// only matches the exact args passed, so arg'd events from other tests would leak.
		\wp_unschedule_hook( self::HOOK );
	}

	protected function tearDown(): void {
		\wp_unschedule_hook( self::HOOK );
		// Each backend wired its own stable filter callback; drop them so schedules do not leak between tests.
		foreach ( $this->backends as $backend ) {
			\remove_filter( 'cron_schedules', array( $backend, 'register_synthetic_schedules' ) );
		}
		$this->backends = array();
		parent::tearDown();
	}

	public function test_schedule_recurring_is_idempotent_across_repeat_calls(): void {
		$backend = $this->backend();

		$first  = $backend->schedule_recurring( self::HOOK, 900 );
		$second = $backend->schedule_recurring( self::HOOK, 900 );

		self::assertInstanceOf( Success::class, $first );
		self::assertInstanceOf( Success::class, $second );
		self::assertSame( 1, $this->count_events( self::HOOK ) );
	}

	public function test_schedule_single_is_idempotent_across_repeat_calls(): void {
		$backend   = $this->backend();
		$timestamp = \time() + 3600;

		$first  = $backend->schedule_single( self::HOOK, $timestamp );
		$second = $backend->schedule_single( self::HOOK, $timestamp );

		self::assertInstanceOf( Success::class, $first );
		self::assertInstanceOf( Success::class, $second );
		self::assertSame( 1, $this->count_events( self::HOOK ) );
	}

	public function test_is_scheduled_and_get_next_scheduled_reflect_a_single_event(): void {
		$backend   = $this->backend();
		$timestamp = \time() + 3600;

		self::assertFalse( $backend->is_scheduled( self::HOOK ) );
		self::assertNull( $backend->get_next_scheduled( self::HOOK ) );

		(void) $backend->schedule_single( self::HOOK, $timestamp );

		self::assertTrue( $backend->is_scheduled( self::HOOK ) );
		self::assertSame( $timestamp, $backend->get_next_scheduled( self::HOOK ) );
	}

	public function test_unschedule_clears_a_scheduled_event(): void {
		$backend = $this->backend();
		self::assertInstanceOf( Success::class, $backend->schedule_single( self::HOOK, \time() + 3600 ) );
		self::assertTrue( $backend->is_scheduled( self::HOOK ) );

		$result = $backend->unschedule( self::HOOK );

		self::assertInstanceOf( Success::class, $result );
		self::assertFalse( $backend->is_scheduled( self::HOOK ) );
	}

	public function test_unschedule_returns_a_failure_when_the_clear_fails(): void {
		$backend = $this->backend();
		(void) $backend->schedule_single( self::HOOK, \time() + 3600 );

		// pre_clear_scheduled_hook short-circuits wp_clear_scheduled_hook; a WP_Error return models a backend
		// (e.g. a wp-cron replacement) failing to unschedule, the exact failure case unschedule must surface.
		$wp_error = new \WP_Error( 'clear_failed', 'Could not clear the hook.' );
		$filter   = static fn (): \WP_Error => $wp_error;
		\add_filter( 'pre_clear_scheduled_hook', $filter );

		try {
			$result = $backend->unschedule( self::HOOK );
		} finally {
			\remove_filter( 'pre_clear_scheduled_hook', $filter );
		}

		self::assertInstanceOf( Failure::class, $result );
		self::assertInstanceOf( SchedulingError::class, $result->error );
		self::assertSame( SchedulingErrorReason::ScheduleFailed, $result->error->reason );
		self::assertSame( 'Could not clear the hook.', $result->error->context['wp_error'] );
	}

	public function test_is_scheduled_rejects_a_non_empty_group(): void {
		$backend = $this->backend();
		self::assertInstanceOf( Success::class, $backend->schedule_single( self::HOOK, \time() + 3600 ) );
		self::assertTrue( $backend->is_scheduled( self::HOOK ) );

		// A grouped schedule can never exist on WP-Cron, so a query naming a group is consistently false.
		self::assertFalse( $backend->is_scheduled( self::HOOK, array(), 'reports' ) );
	}

	public function test_get_next_scheduled_rejects_a_non_empty_group(): void {
		$backend   = $this->backend();
		$timestamp = \time() + 3600;
		self::assertInstanceOf( Success::class, $backend->schedule_single( self::HOOK, $timestamp ) );
		self::assertSame( $timestamp, $backend->get_next_scheduled( self::HOOK ) );

		self::assertNull( $backend->get_next_scheduled( self::HOOK, array(), 'reports' ) );
	}

	public function test_recurring_registers_a_synthetic_named_schedule(): void {
		$backend = $this->backend();

		(void) $backend->schedule_recurring( self::HOOK, 900 );

		$schedules = \wp_get_schedules();
		self::assertArrayHasKey( 'dws_every_900s', $schedules );
		self::assertSame( 900, $schedules['dws_every_900s']['interval'] );
	}

	public function test_args_distinguish_two_concurrent_recurring_events(): void {
		$backend = $this->backend();

		(void) $backend->schedule_recurring( self::HOOK, 900, array( 'a' ) );
		(void) $backend->schedule_recurring( self::HOOK, 900, array( 'b' ) );

		self::assertTrue( $backend->is_scheduled( self::HOOK, array( 'a' ) ) );
		self::assertTrue( $backend->is_scheduled( self::HOOK, array( 'b' ) ) );
		self::assertSame( 2, $this->count_events( self::HOOK ) );
	}

	public function test_schedule_recurring_rejects_a_non_empty_group(): void {
		$result = $this->backend()->schedule_recurring( self::HOOK, 900, array(), null, 'reports' );

		self::assertInstanceOf( Failure::class, $result );
		self::assertInstanceOf( SchedulingError::class, $result->error );
		self::assertSame( SchedulingErrorReason::UnsupportedGroup, $result->error->reason );
		self::assertFalse( $this->backend()->is_scheduled( self::HOOK ) );
	}

	public function test_schedule_single_rejects_a_non_empty_group(): void {
		$result = $this->backend()->schedule_single( self::HOOK, \time() + 3600, array(), 'reports' );

		self::assertInstanceOf( Failure::class, $result );
		self::assertInstanceOf( SchedulingError::class, $result->error );
		self::assertSame( SchedulingErrorReason::UnsupportedGroup, $result->error->reason );
	}

	public function test_unschedule_rejects_a_non_empty_group(): void {
		$result = $this->backend()->unschedule( self::HOOK, array(), 'reports' );

		self::assertInstanceOf( Failure::class, $result );
		self::assertInstanceOf( SchedulingError::class, $result->error );
		self::assertSame( SchedulingErrorReason::UnsupportedGroup, $result->error->reason );
	}

	public function test_schedule_recurring_rejects_a_non_positive_interval(): void {
		$result = $this->backend()->schedule_recurring( self::HOOK, 0 );

		self::assertInstanceOf( Failure::class, $result );
		self::assertInstanceOf( SchedulingError::class, $result->error );
		self::assertSame( SchedulingErrorReason::InvalidInterval, $result->error->reason );
	}

	public function test_register_synthetic_schedules_reconstructs_existing_intervals_from_cron(): void {
		self::assertInstanceOf( Success::class, $this->backend()->schedule_recurring( self::HOOK, 300 ) );

		// A backend with an empty interval registry — the state on every request after the one that
		// scheduled the event — must still surface the synthetic schedule from the cron array, or
		// WordPress cannot resolve 'dws_every_300s' when it reschedules the recurring event.
		$schedules = $this->backend()->register_synthetic_schedules( array() );

		self::assertArrayHasKey( 'dws_every_300s', $schedules );
		self::assertSame( 300, $schedules['dws_every_300s']['interval'] );
	}

	public function test_register_synthetic_schedules_reuses_reconstructed_intervals_within_the_request(): void {
		self::assertInstanceOf( Success::class, $this->backend()->schedule_recurring( self::HOOK, 300 ) );

		$fresh = $this->backend();
		$reads = 0;
		$spy   = static function ( mixed $pre_option ) use ( &$reads ): mixed {
			++$reads;
			return $pre_option;
		};
		\add_filter( 'pre_option_cron', $spy );

		try {
			$fresh->register_synthetic_schedules( array() );
			$fresh->register_synthetic_schedules( array() );
		} finally {
			\remove_filter( 'pre_option_cron', $spy );
		}

		self::assertSame( 1, $reads );
	}

	public function test_register_lifecycle_wires_the_schedule_filter_without_a_schedule_call(): void {
		$backend = $this->backend();
		$backend->register_lifecycle();

		// The filter must be present on a request that only boots — wp-cron itself never schedules —
		// so a recurring event's named schedule resolves when WordPress reschedules it.
		self::assertSame( 10, \has_filter( 'cron_schedules', array( $backend, 'register_synthetic_schedules' ) ) );
	}

	public function test_schedule_recurring_wires_the_filter_on_the_idempotent_fast_path(): void {
		self::assertInstanceOf( Success::class, $this->backend()->schedule_recurring( self::HOOK, 300 ) );

		// A fresh backend re-scheduling an already-scheduled hook takes the idempotent fast-path; it
		// must still wire the schedule filter, or the existing event loses its schedule this request.
		$fresh = $this->backend();
		self::assertInstanceOf( Success::class, $fresh->schedule_recurring( self::HOOK, 300 ) );
		self::assertSame( 10, \has_filter( 'cron_schedules', array( $fresh, 'register_synthetic_schedules' ) ) );
	}

	public function test_wp_reschedule_event_resolves_the_reconstructed_schedule(): void {
		$scheduler = $this->backend();
		self::assertInstanceOf( Success::class, $scheduler->schedule_recurring( self::HOOK, 300 ) );
		$timestamp = \wp_next_scheduled( self::HOOK );
		self::assertIsInt( $timestamp );

		// Simulate a later request: drop the scheduling backend's filter, then wire only the lifecycle.
		\remove_filter( 'cron_schedules', array( $scheduler, 'register_synthetic_schedules' ) );
		$this->backend()->register_lifecycle();

		// wp_reschedule_event re-validates the schedule name through wp_get_schedules(); the reconstructed
		// 'dws_every_300s' must resolve, or it returns a WP_Error and WordPress drops the recurring event.
		self::assertTrue( \wp_reschedule_event( $timestamp, 'dws_every_300s', self::HOOK, array(), true ) );
	}

	public function test_unschedule_with_empty_args_clears_only_the_empty_args_event(): void {
		$backend = $this->backend();
		self::assertInstanceOf( Success::class, $backend->schedule_single( self::HOOK, \time() + 3600 ) );
		self::assertInstanceOf( Success::class, $backend->schedule_single( self::HOOK, \time() + 3600, array( 'a' ) ) );

		// Both backends share one exact-match scope: empty args clears the no-args event only, not
		// every event for the hook.
		self::assertInstanceOf( Success::class, $backend->unschedule( self::HOOK ) );

		self::assertFalse( $backend->is_scheduled( self::HOOK ) );
		self::assertTrue( $backend->is_scheduled( self::HOOK, array( 'a' ) ) );
	}

	private function backend(): WPCronBackend {
		$backend          = new WPCronBackend();
		$this->backends[] = $backend;
		return $backend;
	}

	/**
	 * Counts how many distinct scheduled events exist for a hook across the cron option.
	 *
	 * @param   string $hook Hook to count events for.
	 *
	 * @return  int
	 */
	private function count_events( string $hook ): int {
		$count = 0;
		foreach ( \_get_cron_array() as $events ) {
			if ( isset( $events[ $hook ] ) ) {
				$count += \count( $events[ $hook ] );
			}
		}

		return $count;
	}
}
