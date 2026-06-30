<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Scheduling;

use DeepWebSolutions\Framework\Shared\Result\AbstractResult;
use DeepWebSolutions\Framework\Shared\Result\Success;

/**
 * Backend-agnostic scheduling facade that routes each call to the ready backend.
 *
 * Holds both backends — Action Scheduler and WordPress cron — plus a readiness probe. Scheduling
 * mutations target one backend per call: Action Scheduler when the probe reports it ready, WordPress
 * cron otherwise. The read and clear surface consults both backends, so a job scheduled before Action
 * Scheduler is ready remains visible and cancellable after the preferred backend changes. Construct via
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
		$action_scheduler_result = $this->action_scheduler_backend->unschedule( $hook, $args, $group );
		$wp_cron_result          = $this->wp_cron_backend->unschedule( $hook, $args, $group );

		if ( $action_scheduler_result->is_failure() ) {
			return $action_scheduler_result;
		}
		if ( $wp_cron_result->is_failure() ) {
			return $wp_cron_result;
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
		return $this->action_scheduler_backend->is_scheduled( $hook, $args, $group )
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
		$next = \array_filter(
			array(
				$this->action_scheduler_backend->get_next_scheduled( $hook, $args, $group ),
				$this->wp_cron_backend->get_next_scheduled( $hook, $args, $group ),
			),
			\is_int( ... ),
		);

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
