<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Scheduling;

use DeepWebSolutions\Framework\Shared\Result\AbstractResult;
use DeepWebSolutions\Framework\Shared\Result\Failure;
use DeepWebSolutions\Framework\Shared\Result\Success;
use DeepWebSolutions\Framework\Utilities\Scheduling\Errors\SchedulingError;

/**
 * Strategy for scheduling recurring and one-off actions.
 *
 * Implementations target a concrete scheduler — Action Scheduler when the plugin is
 * active, WordPress cron otherwise. Mutations return a {@see AbstractResult} carrying a
 * {@see SchedulingError} on failure; queries return plain scalars. Scheduling is
 * idempotent: re-scheduling a hook that is already queued with the same args (and group)
 * is a success no-op.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
interface SchedulerBackendInterface {
	/**
	 * Schedules a recurring action firing every $interval seconds.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string      $hook                Hook to fire on each run.
	 * @param   int         $interval            Seconds between runs; must be positive.
	 * @param   list<mixed> $args                Arguments passed to the hook and used to identify the action.
	 * @param   int|null    $first_run_timestamp Unix timestamp of the first run; null schedules from now.
	 * @param   string      $group               Backend grouping label; not all backends support grouping.
	 *
	 * @return  Success<true>|Failure<SchedulingError> Success, or a failure carrying the cause.
	 */
	public function schedule_recurring( string $hook, int $interval, array $args = array(), ?int $first_run_timestamp = null, string $group = '' ): AbstractResult;

	/**
	 * Schedules a one-off action firing once at $timestamp.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string      $hook      Hook to fire.
	 * @param   int         $timestamp Unix timestamp at which to fire.
	 * @param   list<mixed> $args      Arguments passed to the hook and used to identify the action.
	 * @param   string      $group     Backend grouping label; not all backends support grouping.
	 *
	 * @return  Success<true>|Failure<SchedulingError> Success, or a failure carrying the cause.
	 */
	public function schedule_single( string $hook, int $timestamp, array $args = array(), string $group = '' ): AbstractResult;

	/**
	 * Cancels a scheduled action matching the hook, args, and group.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string      $hook  Hook whose scheduled action to cancel.
	 * @param   list<mixed> $args  Arguments the action was scheduled with.
	 * @param   string      $group Backend grouping label the action was scheduled under.
	 *
	 * @return  Success<true>|Failure<SchedulingError> Success, or a failure carrying the cause.
	 */
	public function unschedule( string $hook, array $args = array(), string $group = '' ): AbstractResult;

	/**
	 * Whether an action is currently scheduled for the hook, args, and group.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string      $hook  Hook to check.
	 * @param   list<mixed> $args  Arguments the action was scheduled with.
	 * @param   string      $group Backend grouping label the action was scheduled under.
	 *
	 * @return  bool
	 */
	public function is_scheduled( string $hook, array $args = array(), string $group = '' ): bool;

	/**
	 * Returns the Unix timestamp of the next scheduled run, or null when none is scheduled.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string      $hook  Hook to check.
	 * @param   list<mixed> $args  Arguments the action was scheduled with.
	 * @param   string      $group Backend grouping label the action was scheduled under.
	 *
	 * @return  int|null
	 */
	public function get_next_scheduled( string $hook, array $args = array(), string $group = '' ): ?int;
}
