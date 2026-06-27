<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Unit\Scheduling;

use DeepWebSolutions\Framework\Utilities\Scheduling\Backends\ActionSchedulerBackend;
use DeepWebSolutions\Framework\Utilities\Scheduling\Backends\WPCronBackend;
use DeepWebSolutions\Framework\Utilities\Scheduling\Scheduler;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function DeepWebSolutions\Framework\Utilities\Scheduling\create_scheduler;

#[CoversFunction( 'DeepWebSolutions\Framework\Utilities\Scheduling\create_scheduler' )]
#[UsesClass( Scheduler::class )]
#[UsesClass( ActionSchedulerBackend::class )]
#[UsesClass( WPCronBackend::class )]
final class CreateSchedulerTest extends TestCase {
	public function test_returns_a_scheduler(): void {
		self::assertInstanceOf( Scheduler::class, create_scheduler() );
	}
}
