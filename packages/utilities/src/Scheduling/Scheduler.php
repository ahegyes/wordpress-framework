<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Scheduling;

use DeepWebSolutions\Framework\Shared\Result\AbstractResult;
use DeepWebSolutions\Framework\Shared\Result\Success;

/**
 * Backend-agnostic scheduling facade that routes schedule writes to the ready backend.
 *
 * Holds both backends — Action Scheduler and WordPress cron — plus a readiness probe. Schedule
 * writes target one backend per call: Action Scheduler when the probe reports it ready, WordPress
 * cron otherwise. The read and clear surface spans both backends — WordPress cron always, Action
 * Scheduler only while the probe reports it ready — so a job scheduled before Action Scheduler is
 * ready remains visible and cancellable after the preferred backend changes. Construct via
 * {@see create_scheduler()} for the default wiring, or inject backends and a probe directly.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class Scheduler implements SchedulerBackendInterface {
	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   SchedulerBackendInterface $action_scheduler_backend  Backend targeting Action Scheduler.
	 * @param   SchedulerBackendInterface $wp_cron_backend           Backend targeting WordPress cron.
	 * @param   \Closure(): bool          $is_action_scheduler_ready Predicate reporting whether Action Scheduler is ready for schedule, clear, and query calls.
	 */
	public function __construct(
		protected SchedulerBackendInterface $action_scheduler_backend,
		protected SchedulerBackendInterface $wp_cron_backend,
		protected \Closure $is_action_scheduler_ready,
	) {}

	// endregion

	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	#[\NoDiscard( 'a scheduling failure must be handled, not dropped' )]
	public function schedule_recurring( string $hook, int $interval, array $args = array(), ?int $first_run_timestamp = null, string $group = '' ): AbstractResult {
		return $this->backend()->schedule_recurring( $hook, $interval, $args, $first_run_timestamp, $group );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	#[\NoDiscard( 'a scheduling failure must be handled, not dropped' )]
	public function schedule_single( string $hook, int $timestamp, array $args = array(), string $group = '' ): AbstractResult {
		return $this->backend()->schedule_single( $hook, $timestamp, $args, $group );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	#[\NoDiscard( 'a scheduling failure must be handled, not dropped' )]
	public function unschedule( string $hook, array $args = array(), string $group = '' ): AbstractResult {
		$results = array();
		if ( $this->should_consult_action_scheduler() ) {
			$results[] = $this->action_scheduler_backend->unschedule( $hook, $args, $group );
		}
		$results[] = $this->wp_cron_backend->unschedule( $hook, $args, $group );

		foreach ( $results as $result ) {
			if ( $result->is_failure() ) {
				return $result;
			}
		}

		return Success::from( true );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function is_scheduled( string $hook, array $args = array(), string $group = '' ): bool {
		return ( $this->should_consult_action_scheduler() && $this->action_scheduler_backend->is_scheduled( $hook, $args, $group ) )
			|| $this->wp_cron_backend->is_scheduled( $hook, $args, $group );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function get_next_scheduled( string $hook, array $args = array(), string $group = '' ): ?int {
		$timestamps = array( $this->wp_cron_backend->get_next_scheduled( $hook, $args, $group ) );
		if ( $this->should_consult_action_scheduler() ) {
			$timestamps[] = $this->action_scheduler_backend->get_next_scheduled( $hook, $args, $group );
		}

		$next = \array_filter( $timestamps, \is_int( ... ) );

		return array() === $next ? null : \min( $next );
	}

	/**
	 * {@inheritDoc}
	 *
	 * Wires both backends so the chosen one is ready whichever the readiness probe later selects.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function register_lifecycle(): void {
		$this->action_scheduler_backend->register_lifecycle();
		$this->wp_cron_backend->register_lifecycle();
	}

	// endregion

	// region HELPERS

	/**
	 * Selects the backend for a schedule write: Action Scheduler when ready, otherwise WordPress cron.
	 *
	 * Clear and query paths do not select one backend — they span WordPress cron and, when
	 * {@see self::should_consult_action_scheduler()}, Action Scheduler.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  SchedulerBackendInterface
	 */
	protected function backend(): SchedulerBackendInterface {
		return $this->should_consult_action_scheduler()
			? $this->action_scheduler_backend
			: $this->wp_cron_backend;
	}

	/**
	 * Whether the current schedule, clear, or query call may consult Action Scheduler.
	 *
	 * Action Scheduler's procedural API returns no-op values before its datastore is ready, so the
	 * schedule, clear, and query paths all gate Action Scheduler calls through this one probe. When
	 * the probe fails there is nothing consultable: a dormant Action Scheduler store, if one persists
	 * while its plugin is deactivated, is outside the facade's reach, so clear paths report the
	 * outcome of the reachable backends alone.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  bool
	 */
	protected function should_consult_action_scheduler(): bool {
		return ( $this->is_action_scheduler_ready )();
	}

	// endregion
}
