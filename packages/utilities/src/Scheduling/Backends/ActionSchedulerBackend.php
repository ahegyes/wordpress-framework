<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Scheduling\Backends;

use DeepWebSolutions\Framework\Shared\Result\AbstractResult;
use DeepWebSolutions\Framework\Shared\Result\Failure;
use DeepWebSolutions\Framework\Shared\Result\Success;
use DeepWebSolutions\Framework\Utilities\Scheduling\Errors\SchedulingError;
use DeepWebSolutions\Framework\Utilities\Scheduling\SchedulerBackendInterface;
use DeepWebSolutions\Framework\Utilities\Scheduling\SchedulingErrorReason;
use Psr\Log\LoggerInterface;

/**
 * Scheduler backend over Action Scheduler.
 *
 * Every as_* call is guarded by function_exists so a missing Action Scheduler fails
 * cleanly rather than fataling. Idempotency is enforced by the args-aware
 * as_has_scheduled_action() guard rather than Action Scheduler's $unique flag, which
 * keys on hook plus group only and ignores args — two schedules differing only in args
 * must both be allowed. The optional logger records the absent-scheduler condition.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class ActionSchedulerBackend implements SchedulerBackendInterface {
	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   LoggerInterface|null $logger Optional PSR-3 logger for the absent-scheduler condition.
	 */
	public function __construct(
		protected ?LoggerInterface $logger = null,
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
		if ( ! $this->is_available() ) {
			return $this->unavailable();
		}
		if ( $interval <= 0 ) {
			return Failure::from(
				new SchedulingError(
					SchedulingErrorReason::InvalidInterval,
					'A recurring schedule requires a positive interval.',
					array( 'interval' => $interval ),
				),
			);
		}
		if ( \as_has_scheduled_action( $hook, $args, $group ) ) {
			return Success::from( true );
		}

		$action_id = \as_schedule_recurring_action( $first_run_timestamp ?? \time(), $interval, $hook, $args, $group, false );
		return $this->result_for_action_id( $action_id, $hook );
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
		if ( ! $this->is_available() ) {
			return $this->unavailable();
		}
		if ( \as_has_scheduled_action( $hook, $args, $group ) ) {
			return Success::from( true );
		}

		$action_id = \as_schedule_single_action( $timestamp, $hook, $args, $group, false );
		return $this->result_for_action_id( $action_id, $hook );
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
		if ( ! $this->is_available() ) {
			return $this->unavailable();
		}

		\as_unschedule_action( $hook, $args, $group );
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
		if ( ! $this->is_available() ) {
			return false;
		}

		return \as_has_scheduled_action( $hook, $args, $group );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function get_next_scheduled( string $hook, array $args = array(), string $group = '' ): ?int {
		if ( ! $this->is_available() ) {
			return null;
		}

		$next = \as_next_scheduled_action( $hook, $args, $group );
		return \is_int( $next ) ? $next : null;
	}

	// endregion

	// region HELPERS

	/**
	 * Whether Action Scheduler's scheduling API is loaded.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  bool
	 */
	protected function is_available(): bool {
		return \function_exists( 'as_schedule_recurring_action' );
	}

	/**
	 * Builds the failure returned when Action Scheduler is not loaded, logging the condition.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  Failure<SchedulingError>
	 */
	protected function unavailable(): Failure {
		$message = 'Action Scheduler is not loaded; cannot schedule the action.';
		$this->logger?->error( $message );

		return Failure::from(
			new SchedulingError( SchedulingErrorReason::ActionSchedulerNotLoaded, $message ),
		);
	}

	/**
	 * Maps an Action Scheduler action id to a result — zero is a scheduling failure.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int    $action_id Action id returned by Action Scheduler; zero on error.
	 * @param   string $hook      Hook that was being scheduled, for the error context.
	 *
	 * @return  Success<true>|Failure<SchedulingError> Success, or a failure when the id is zero.
	 */
	protected function result_for_action_id( int $action_id, string $hook ): AbstractResult {
		if ( 0 === $action_id ) {
			return Failure::from(
				new SchedulingError(
					SchedulingErrorReason::ScheduleFailed,
					'Action Scheduler returned a zero action id when scheduling the action.',
					array( 'hook' => $hook ),
				),
			);
		}

		return Success::from( true );
	}

	// endregion
}
