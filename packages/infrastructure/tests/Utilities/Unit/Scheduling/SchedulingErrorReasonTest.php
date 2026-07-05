<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Unit\Scheduling;

use DeepWebSolutions\Framework\Utilities\Scheduling\SchedulingErrorReason;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( SchedulingErrorReason::class )]
final class SchedulingErrorReasonTest extends TestCase {
	public function test_backing_values_are_stable(): void {
		self::assertSame( 'action_scheduler_not_loaded', SchedulingErrorReason::ActionSchedulerNotLoaded->value );
		self::assertSame( 'unsupported_group', SchedulingErrorReason::UnsupportedGroup->value );
		self::assertSame( 'invalid_interval', SchedulingErrorReason::InvalidInterval->value );
		self::assertSame( 'schedule_failed', SchedulingErrorReason::ScheduleFailed->value );
	}

	public function test_holds_exactly_the_expected_cases(): void {
		self::assertSame(
			array( 'ActionSchedulerNotLoaded', 'UnsupportedGroup', 'InvalidInterval', 'ScheduleFailed' ),
			\array_map( static fn ( SchedulingErrorReason $reason ): string => $reason->name, SchedulingErrorReason::cases() ),
		);
	}
}
