<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Unit\Core\Scheduling\Backends;

use DeepWebSolutions\Framework\Scheduling\Backends\ActionSchedulerBackend;
use DeepWebSolutions\Framework\Scheduling\Backends\BackendState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( ActionSchedulerBackend::class )]
final class ActionSchedulerBackendTest extends TestCase {
	public function test_action_scheduler_is_absent_when_its_functions_are_not_loaded(): void {
		self::assertSame( BackendState::Absent, ( new ActionSchedulerBackend( 'acme' ) )->get_state() );
	}
}
