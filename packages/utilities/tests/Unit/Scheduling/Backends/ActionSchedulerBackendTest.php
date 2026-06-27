<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Unit\Scheduling\Backends;

use DeepWebSolutions\Framework\Shared\Result\Failure;
use DeepWebSolutions\Framework\Utilities\Scheduling\Backends\ActionSchedulerBackend;
use DeepWebSolutions\Framework\Utilities\Scheduling\Errors\SchedulingError;
use DeepWebSolutions\Framework\Utilities\Scheduling\SchedulingErrorReason;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Action Scheduler is absent in the unit context, so every mutation reports the
 * not-loaded failure and every query degrades safely. The live as_* behaviour is
 * exercised in the integration suite.
 */
#[CoversClass( ActionSchedulerBackend::class )]
#[UsesClass( SchedulingError::class )]
#[UsesClass( SchedulingErrorReason::class )]
#[UsesClass( Failure::class )]
final class ActionSchedulerBackendTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		if ( \function_exists( 'as_schedule_recurring_action' ) ) {
			self::markTestSkipped( 'Action Scheduler is loaded; the not-loaded path is unobservable.' );
		}
	}

	public function test_schedule_recurring_fails_when_action_scheduler_absent(): void {
		$result = ( new ActionSchedulerBackend() )->schedule_recurring( 'dws_hook', 300 );

		self::assertInstanceOf( Failure::class, $result );
		self::assertInstanceOf( SchedulingError::class, $result->error );
		self::assertSame( SchedulingErrorReason::ActionSchedulerNotLoaded, $result->error->reason );
	}

	public function test_schedule_single_fails_when_action_scheduler_absent(): void {
		$result = ( new ActionSchedulerBackend() )->schedule_single( 'dws_hook', 1700000000 );

		self::assertInstanceOf( Failure::class, $result );
		self::assertInstanceOf( SchedulingError::class, $result->error );
		self::assertSame( SchedulingErrorReason::ActionSchedulerNotLoaded, $result->error->reason );
	}

	public function test_unschedule_fails_when_action_scheduler_absent(): void {
		$result = ( new ActionSchedulerBackend() )->unschedule( 'dws_hook' );

		self::assertInstanceOf( Failure::class, $result );
		self::assertInstanceOf( SchedulingError::class, $result->error );
		self::assertSame( SchedulingErrorReason::ActionSchedulerNotLoaded, $result->error->reason );
	}

	public function test_queries_degrade_safely_when_action_scheduler_absent(): void {
		$backend = new ActionSchedulerBackend();

		self::assertFalse( $backend->is_scheduled( 'dws_hook' ) );
		self::assertNull( $backend->get_next_scheduled( 'dws_hook' ) );
	}

	public function test_logs_the_not_loaded_condition_when_a_logger_is_given(): void {
		$logger = $this->createMock( LoggerInterface::class );
		$logger->expects( self::once() )->method( 'error' );

		( new ActionSchedulerBackend( $logger ) )->schedule_recurring( 'dws_hook', 300 );
	}
}
