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

		(void) $backend->schedule_single( self::HOOK, \time() + 3600, array(), self::GROUP );

		self::assertTrue( $backend->is_scheduled( self::HOOK, array(), self::GROUP ) );
	}

	public function test_get_next_scheduled_returns_the_pending_timestamp(): void {
		$backend   = new ActionSchedulerBackend();
		$timestamp = \time() + 3600;

		self::assertNull( $backend->get_next_scheduled( self::HOOK, array(), self::GROUP ) );

		(void) $backend->schedule_single( self::HOOK, $timestamp, array(), self::GROUP );

		self::assertSame( $timestamp, $backend->get_next_scheduled( self::HOOK, array(), self::GROUP ) );
	}

	public function test_unschedule_cancels_a_scheduled_action(): void {
		$backend = new ActionSchedulerBackend();
		self::assertInstanceOf( Success::class, $backend->schedule_single( self::HOOK, \time() + 3600, array(), self::GROUP ) );
		self::assertTrue( $backend->is_scheduled( self::HOOK, array(), self::GROUP ) );

		$result = $backend->unschedule( self::HOOK, array(), self::GROUP );

		self::assertInstanceOf( Success::class, $result );
		self::assertFalse( $backend->is_scheduled( self::HOOK, array(), self::GROUP ) );
	}

	public function test_args_distinguish_two_concurrent_actions(): void {
		$backend = new ActionSchedulerBackend();

		(void) $backend->schedule_single( self::HOOK, \time() + 3600, array( 'a' ), self::GROUP );
		(void) $backend->schedule_single( self::HOOK, \time() + 3600, array( 'b' ), self::GROUP );

		self::assertSame( 2, $this->count_pending( self::HOOK, self::GROUP ) );
	}

	public function test_unschedule_with_empty_args_clears_only_the_empty_args_action(): void {
		$backend = new ActionSchedulerBackend();
		(void) $backend->schedule_single( self::HOOK, \time() + 3600 );
		(void) $backend->schedule_single( self::HOOK, \time() + 3600, array( 'a' ) );

		// Empty args matches the no-args action exactly, consistent with schedule/is_scheduled — it does
		// not wildcard to every action for the hook (which the broad cancel-by-hook fast path would do).
		self::assertInstanceOf( Success::class, $backend->unschedule( self::HOOK ) );

		self::assertFalse( $backend->is_scheduled( self::HOOK ) );
		self::assertTrue( $backend->is_scheduled( self::HOOK, array( 'a' ) ) );
	}

	public function test_unschedule_clears_duplicate_actions_with_the_same_hook_args_group(): void {
		// Two actions can share a hook+args+group when scheduled outside the backend's idempotency guard;
		// unschedule must clear all of them, not just one.
		\as_schedule_single_action( \time() + 3600, self::HOOK, array( 'x' ), self::GROUP );
		\as_schedule_single_action( \time() + 3600, self::HOOK, array( 'x' ), self::GROUP );

		self::assertInstanceOf( Success::class, ( new ActionSchedulerBackend() )->unschedule( self::HOOK, array( 'x' ), self::GROUP ) );

		self::assertFalse( ( new ActionSchedulerBackend() )->is_scheduled( self::HOOK, array( 'x' ), self::GROUP ) );
	}

	public function test_unschedule_with_specific_args_cancels_only_the_matching_action(): void {
		$backend = new ActionSchedulerBackend();
		(void) $backend->schedule_single( self::HOOK, \time() + 3600, array( 'a' ), self::GROUP );
		(void) $backend->schedule_single( self::HOOK, \time() + 3600, array( 'b' ), self::GROUP );

		self::assertInstanceOf( Success::class, $backend->unschedule( self::HOOK, array( 'a' ), self::GROUP ) );

		self::assertFalse( $backend->is_scheduled( self::HOOK, array( 'a' ), self::GROUP ) );
		self::assertTrue( $backend->is_scheduled( self::HOOK, array( 'b' ), self::GROUP ) );
	}

	public function test_unschedule_reports_a_failure_when_a_matching_action_cannot_be_cleared(): void {
		$backend = new ActionSchedulerBackend();
		self::assertInstanceOf( Success::class, $backend->schedule_single( self::HOOK, \time() + 3600, array(), self::GROUP ) );

		// Force the action to running: the cancel loop removes only pending actions, but the verify counts
		// running ones too, so an action that cannot be cancelled surfaces as a Failure.
		$id = \as_get_scheduled_actions(
			array( 'hook' => self::HOOK, 'group' => self::GROUP, 'status' => \ActionScheduler_Store::STATUS_PENDING, 'per_page' => 1 ),
			'ids',
		)[0] ?? null;
		self::assertNotNull( $id );
		\ActionScheduler_Store::instance()->log_execution( (string) $id );

		$result = ( new ActionSchedulerBackend() )->unschedule( self::HOOK, array(), self::GROUP );

		self::assertInstanceOf( Failure::class, $result );
		self::assertInstanceOf( SchedulingError::class, $result->error );
		self::assertSame( SchedulingErrorReason::ScheduleFailed, $result->error->reason );
	}

	/**
	 * Deletes every action for the test hook, regardless of args, group, or status.
	 *
	 * Cancel-by-hook reaps only pending actions; a test can leave an action in-progress, so every
	 * action for the hook is deleted to keep the shared store isolated between tests.
	 *
	 * @return  void
	 */
	private function clear_hook(): void {
		foreach ( \as_get_scheduled_actions( array( 'hook' => self::HOOK, 'per_page' => -1 ), 'ids' ) as $id ) {
			\ActionScheduler_Store::instance()->delete_action( (string) $id );
		}
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
