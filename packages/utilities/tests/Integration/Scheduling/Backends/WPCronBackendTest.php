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

		$backend->schedule_single( self::HOOK, $timestamp );

		self::assertTrue( $backend->is_scheduled( self::HOOK ) );
		self::assertSame( $timestamp, $backend->get_next_scheduled( self::HOOK ) );
	}

	public function test_unschedule_clears_a_scheduled_event(): void {
		$backend = $this->backend();
		$backend->schedule_single( self::HOOK, \time() + 3600 );

		$result = $backend->unschedule( self::HOOK );

		self::assertInstanceOf( Success::class, $result );
		self::assertFalse( $backend->is_scheduled( self::HOOK ) );
	}

	public function test_recurring_registers_a_synthetic_named_schedule(): void {
		$backend = $this->backend();

		$backend->schedule_recurring( self::HOOK, 900 );

		$schedules = \wp_get_schedules();
		self::assertArrayHasKey( 'dws_every_900s', $schedules );
		self::assertSame( 900, $schedules['dws_every_900s']['interval'] );
	}

	public function test_args_distinguish_two_concurrent_recurring_events(): void {
		$backend = $this->backend();

		$backend->schedule_recurring( self::HOOK, 900, array( 'a' ) );
		$backend->schedule_recurring( self::HOOK, 900, array( 'b' ) );

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
