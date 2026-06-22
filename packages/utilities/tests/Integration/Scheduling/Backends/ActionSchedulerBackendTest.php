<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Integration\Scheduling\Backends;

use DeepWebSolutions\Framework\Shared\Result\Failure;
use DeepWebSolutions\Framework\Shared\Result\Success;
use DeepWebSolutions\Framework\Utilities\Scheduling\Backends\ActionSchedulerBackend;
use DeepWebSolutions\Framework\Utilities\Scheduling\Errors\SchedulingError;
use DeepWebSolutions\Framework\Utilities\Scheduling\SchedulingErrorReason;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( ActionSchedulerBackend::class )]
#[UsesClass( SchedulingError::class )]
#[UsesClass( SchedulingErrorReason::class )]
#[UsesClass( Success::class )]
#[UsesClass( Failure::class )]
final class ActionSchedulerBackendTest extends TestCase {
	private const HOOK  = 'dws_test_as_hook';
	private const GROUP = 'dws_test_as_group';

	protected function setUp(): void {
		parent::setUp();
		if ( ! \function_exists( 'as_schedule_recurring_action' ) ) {
			self::markTestSkipped( 'Action Scheduler is not loaded in this environment.' );
		}
		$this->clear_hook();
	}

	protected function tearDown(): void {
		$this->clear_hook();
		parent::tearDown();
	}

	public function test_schedule_recurring_is_idempotent_across_repeat_calls(): void {
		$backend = new ActionSchedulerBackend();

		$first  = $backend->schedule_recurring( self::HOOK, 300, array(), null, self::GROUP );
		$second = $backend->schedule_recurring( self::HOOK, 300, array(), null, self::GROUP );

		self::assertInstanceOf( Success::class, $first );
		self::assertInstanceOf( Success::class, $second );
		self::assertSame( 1, $this->count_pending( self::HOOK, self::GROUP ) );
	}

	public function test_schedule_single_is_idempotent_across_repeat_calls(): void {
		$backend = new ActionSchedulerBackend();

		$first  = $backend->schedule_single( self::HOOK, \time() + 3600, array(), self::GROUP );
		$second = $backend->schedule_single( self::HOOK, \time() + 3600, array(), self::GROUP );

		self::assertInstanceOf( Success::class, $first );
		self::assertInstanceOf( Success::class, $second );
		self::assertSame( 1, $this->count_pending( self::HOOK, self::GROUP ) );
	}

	public function test_is_scheduled_reflects_a_scheduled_action(): void {
		$backend = new ActionSchedulerBackend();

		self::assertFalse( $backend->is_scheduled( self::HOOK, array(), self::GROUP ) );

		$backend->schedule_single( self::HOOK, \time() + 3600, array(), self::GROUP );

		self::assertTrue( $backend->is_scheduled( self::HOOK, array(), self::GROUP ) );
	}

	public function test_get_next_scheduled_returns_the_pending_timestamp(): void {
		$backend   = new ActionSchedulerBackend();
		$timestamp = \time() + 3600;

		self::assertNull( $backend->get_next_scheduled( self::HOOK, array(), self::GROUP ) );

		$backend->schedule_single( self::HOOK, $timestamp, array(), self::GROUP );

		self::assertSame( $timestamp, $backend->get_next_scheduled( self::HOOK, array(), self::GROUP ) );
	}

	public function test_unschedule_cancels_a_scheduled_action(): void {
		$backend = new ActionSchedulerBackend();
		$backend->schedule_single( self::HOOK, \time() + 3600, array(), self::GROUP );

		$result = $backend->unschedule( self::HOOK, array(), self::GROUP );

		self::assertInstanceOf( Success::class, $result );
		self::assertFalse( $backend->is_scheduled( self::HOOK, array(), self::GROUP ) );
	}

	public function test_args_distinguish_two_concurrent_actions(): void {
		$backend = new ActionSchedulerBackend();

		$backend->schedule_single( self::HOOK, \time() + 3600, array( 'a' ), self::GROUP );
		$backend->schedule_single( self::HOOK, \time() + 3600, array( 'b' ), self::GROUP );

		self::assertSame( 2, $this->count_pending( self::HOOK, self::GROUP ) );
	}

	/**
	 * Cancels every action for the test hook, regardless of args or group.
	 *
	 * Passing an empty group takes Action Scheduler's cancel_actions_by_hook() fast path,
	 * which clears all args — unlike the hook+group form, which only matches empty args.
	 *
	 * @return  void
	 */
	private function clear_hook(): void {
		\as_unschedule_all_actions( self::HOOK );
	}

	/**
	 * Counts pending Action Scheduler actions for a hook and group.
	 *
	 * @param   string $hook  Hook to count.
	 * @param   string $group Group to count within.
	 *
	 * @return  int
	 */
	private function count_pending( string $hook, string $group ): int {
		return \count(
			\as_get_scheduled_actions(
				array(
					'hook'     => $hook,
					'group'    => $group,
					'status'   => \ActionScheduler_Store::STATUS_PENDING,
					'per_page' => -1,
				),
				'ids',
			),
		);
	}
}
