<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Scheduling;

use DeepWebSolutions\Framework\Shared\Result\AbstractResult;

/**
 * Backend-agnostic scheduling facade.
 *
 * Delegates every call to the injected {@see SchedulerBackendInterface}, which targets
 * either Action Scheduler or WordPress cron. Construct via {@see select_scheduler_backend()}
 * to auto-select the backend, or inject a specific backend (or a test fake) directly.
 * Components typehint this class — or the interface — and schedule through it.
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
	 * @param   SchedulerBackendInterface $backend Backend that performs the scheduling.
	 */
	public function __construct(
		protected SchedulerBackendInterface $backend,
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
		return $this->backend->schedule_recurring( $hook, $interval, $args, $first_run_timestamp, $group );
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
		return $this->backend->schedule_single( $hook, $timestamp, $args, $group );
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
		return $this->backend->unschedule( $hook, $args, $group );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function is_scheduled( string $hook, array $args = array(), string $group = '' ): bool {
		return $this->backend->is_scheduled( $hook, $args, $group );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function get_next_scheduled( string $hook, array $args = array(), string $group = '' ): ?int {
		return $this->backend->get_next_scheduled( $hook, $args, $group );
	}

	// endregion

	// region GETTERS

	/**
	 * Returns the backend this facade delegates to.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  SchedulerBackendInterface
	 */
	public function get_backend(): SchedulerBackendInterface {
		return $this->backend;
	}

	// endregion
}
