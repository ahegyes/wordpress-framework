<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Unit\Scheduling;

use DeepWebSolutions\Framework\Shared\Result\Failure;
use DeepWebSolutions\Framework\Utilities\Scheduling\Backends\ActionSchedulerBackend;
use DeepWebSolutions\Framework\Utilities\Scheduling\Backends\WPCronBackend;
use DeepWebSolutions\Framework\Utilities\Scheduling\Errors\SchedulingError;
use DeepWebSolutions\Framework\Utilities\Scheduling\Scheduler;
use DeepWebSolutions\Framework\Utilities\Scheduling\SchedulingErrorReason;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function DeepWebSolutions\Framework\Utilities\Scheduling\create_scheduler;

#[CoversFunction( 'DeepWebSolutions\Framework\Utilities\Scheduling\create_scheduler' )]
#[UsesClass( Scheduler::class )]
#[UsesClass( ActionSchedulerBackend::class )]
#[UsesClass( WPCronBackend::class )]
#[UsesClass( Failure::class )]
#[UsesClass( SchedulingError::class )]
#[UsesClass( SchedulingErrorReason::class )]
final class CreateSchedulerTest extends TestCase {
	public function test_returns_a_scheduler(): void {
		self::assertInstanceOf( Scheduler::class, create_scheduler() );
	}

	public function test_ready_probe_routes_to_the_action_scheduler_backend(): void {
		$scheduler = create_scheduler( null, static fn (): bool => true );

		$result = $scheduler->schedule_single( 'dws_hook', 1700000000 );

		self::assertInstanceOf( Failure::class, $result );
		$error = $result->error;
		self::assertInstanceOf( SchedulingError::class, $error );
		self::assertSame( SchedulingErrorReason::ActionSchedulerNotLoaded, $error->reason );
	}

	public function test_unready_probe_routes_to_the_wp_cron_backend(): void {
		$scheduler = create_scheduler( null, static fn (): bool => false );

		$result = $scheduler->schedule_single( 'dws_hook', 1700000000, array(), 'grp' );

		self::assertInstanceOf( Failure::class, $result );
		$error = $result->error;
		self::assertInstanceOf( SchedulingError::class, $error );
		self::assertSame( SchedulingErrorReason::UnsupportedGroup, $error->reason );
	}
}
