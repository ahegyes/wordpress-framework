<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Scheduling;

use DeepWebSolutions\Framework\Shared\Result\AbstractResult;

/**
 * Backend-agnostic scheduling facade that routes each call to the ready backend.
 *
 * Holds both backends — Action Scheduler and WordPress cron — plus a readiness probe, and picks
 * the target per call: Action Scheduler when the probe reports it ready, WordPress cron otherwise.
 * The choice has to be per-call because Action Scheduler initializes after this facade is
 * constructed — its data store is ready only from 'action_scheduler_init', later than the
 * 'plugins_loaded' wiring — so a schedule requested before then still succeeds via WordPress cron
 * while a later one prefers Action Scheduler. Because selection is per call, a recurring hook
 * scheduled before Action Scheduler is ready lands on WordPress cron and stays invisible to a later
 * Action-Scheduler-routed query or unschedule, which would orphan it; schedule a given recurring hook
 * on one side of that boundary — prefer 'init' or later — not from a boot-time installer. Construct
 * via {@see create_scheduler()} for the default wiring, or inject backends and a probe directly (a
 * test fake included). Components typehint this class — or the interface — and schedule through it.
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
	 * @param   \Closure(): bool          $is_action_scheduler_ready Predicate reporting whether Action Scheduler is ready to schedule.
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
		return $this->backend()->unschedule( $hook, $args, $group );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function is_scheduled( string $hook, array $args = array(), string $group = '' ): bool {
		return $this->backend()->is_scheduled( $hook, $args, $group );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function get_next_scheduled( string $hook, array $args = array(), string $group = '' ): ?int {
		return $this->backend()->get_next_scheduled( $hook, $args, $group );
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
	 * Selects the backend for the current call: Action Scheduler when ready, otherwise WordPress cron.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  SchedulerBackendInterface
	 */
	protected function backend(): SchedulerBackendInterface {
		return ( $this->is_action_scheduler_ready )()
			? $this->action_scheduler_backend
			: $this->wp_cron_backend;
	}

	// endregion
}
