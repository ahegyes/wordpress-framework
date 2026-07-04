<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Unit\Scheduling;

use DeepWebSolutions\Framework\Shared\Result\Failure;
use DeepWebSolutions\Framework\Utilities\Scheduling\Backends\ActionSchedulerBackend;
use DeepWebSolutions\Framework\Utilities\Scheduling\Backends\WPCronBackend;
use DeepWebSolutions\Framework\Utilities\Scheduling\Errors\SchedulingError;
use DeepWebSolutions\Framework\Utilities\Scheduling\Scheduler;
use DeepWebSolutions\Framework\Utilities\Scheduling\SchedulingErrorReason;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( Scheduler::class )]
#[UsesClass( ActionSchedulerBackend::class )]
#[UsesClass( WPCronBackend::class )]
#[UsesClass( Failure::class )]
#[UsesClass( SchedulingError::class )]
#[UsesClass( SchedulingErrorReason::class )]
final class SchedulerDefaultWiringTest extends TestCase {
	public function test_an_omitted_backend_list_yields_the_wp_cron_baseline(): void {
		$result = new Scheduler()->schedule_single( 'dws_hook', 1700000000, array(), 'grp' );

		self::assertInstanceOf( Failure::class, $result );
		$error = $result->error;
		self::assertInstanceOf( SchedulingError::class, $error );
		self::assertSame( SchedulingErrorReason::UnsupportedGroup, $error->reason );
	}

	public function test_an_explicit_action_scheduler_backend_is_consulted_when_ready(): void {
		$scheduler = new Scheduler(
			array(
				new ActionSchedulerBackend( null, static fn (): bool => true ),
				new WPCronBackend(),
			),
		);

		$result = $scheduler->schedule_single( 'dws_hook', 1700000000 );

		self::assertInstanceOf( Failure::class, $result );
		$error = $result->error;
		self::assertInstanceOf( SchedulingError::class, $error );
		self::assertSame( SchedulingErrorReason::ActionSchedulerNotLoaded, $error->reason );
	}

	public function test_an_unready_action_scheduler_backend_falls_back_to_wp_cron(): void {
		$scheduler = new Scheduler(
			array(
				new ActionSchedulerBackend( null, static fn (): bool => false ),
				new WPCronBackend(),
			),
		);

		$result = $scheduler->schedule_single( 'dws_hook', 1700000000, array(), 'grp' );

		self::assertInstanceOf( Failure::class, $result );
		$error = $result->error;
		self::assertInstanceOf( SchedulingError::class, $error );
		self::assertSame( SchedulingErrorReason::UnsupportedGroup, $error->reason );
	}
}
