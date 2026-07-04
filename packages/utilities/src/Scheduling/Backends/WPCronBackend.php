<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Scheduling\Backends;

use DeepWebSolutions\Framework\Shared\Result\AbstractResult;
use DeepWebSolutions\Framework\Shared\Result\Failure;
use DeepWebSolutions\Framework\Shared\Result\Success;
use DeepWebSolutions\Framework\Utilities\Scheduling\Errors\SchedulingError;
use DeepWebSolutions\Framework\Utilities\Scheduling\SchedulerBackendInterface;
use DeepWebSolutions\Framework\Utilities\Scheduling\SchedulingErrorReason;
use Psr\Log\LoggerInterface;
use WP_Error;

/**
 * Scheduler backend over WordPress cron.
 *
 * WordPress cron addresses a recurring event by a named schedule, not a raw interval, so
 * each distinct interval gets a synthetic schedule 'dws_every_{N}s' injected through the
 * 'cron_schedules' filter. WordPress resolves that schedule again whenever it reschedules the
 * event — on a request that never touches this backend, wp-cron included — so the caller wires
 * the filter through {@see self::register_lifecycle()} on each load, independent of any schedule
 * call, and its callback rebuilds the interval set from the cron array so an event scheduled on
 * an earlier request still resolves. WordPress cron has no grouping, so a non-empty group is
 * rejected on schedule writes and treated as absent by read and clear paths.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class WPCronBackend implements SchedulerBackendInterface {
	// region FIELDS AND CONSTANTS

	/**
	 * Optional PSR-3 logger for failure conditions.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     LoggerInterface|null
	 */
	protected ?LoggerInterface $logger;

	/**
	 * Intervals (in seconds) for which a synthetic schedule has been registered, used as a set.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     array<int, bool>
	 */
	protected array $registered_intervals = array();

	/**
	 * Whether the 'cron_schedules' filter callback has been wired this request.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     bool
	 */
	protected bool $schedules_filter_registered = false;

	/**
	 * Request-local cache of intervals reconstructed from the cron array.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     ?list<int>
	 */
	protected ?array $scheduled_intervals = null;

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   LoggerInterface|null $logger Optional PSR-3 logger for failure conditions.
	 */
	public function __construct( ?LoggerInterface $logger = null ) {
		$this->logger = $logger;
	}

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
		$rejection = $this->reject_group( $group );
		if ( null !== $rejection ) {
			return $rejection;
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
		// Wire the schedule filter before the idempotency fast-path: an already-scheduled hook must
		// still gain a resolvable synthetic schedule on this request so WordPress can reschedule it.
		$schedule_name = $this->ensure_schedule( $interval );
		if ( false !== \wp_next_scheduled( $hook, $args ) ) {
			return Success::from( true );
		}

		$scheduled = \wp_schedule_event( $first_run_timestamp ?? \time(), $schedule_name, $hook, $args, true );
		return $this->result_for_schedule( $scheduled, $hook );
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
		$rejection = $this->reject_group( $group );
		if ( null !== $rejection ) {
			return $rejection;
		}
		if ( false !== \wp_next_scheduled( $hook, $args ) ) {
			return Success::from( true );
		}

		$scheduled = \wp_schedule_single_event( $timestamp, $hook, $args, true );
		return $this->result_for_schedule( $scheduled, $hook );
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
		// A grouped schedule can never exist on WP-Cron, so clearing a group is consistently a success no-op.
		if ( '' !== $group ) {
			return Success::from( true );
		}

		$this->scheduled_intervals = null;
		$cleared                   = \wp_clear_scheduled_hook( $hook, $args, true );
		return $this->result_for_unschedule( $cleared, $hook );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function is_scheduled( string $hook, array $args = array(), string $group = '' ): bool {
		// A grouped schedule can never exist on WP-Cron, so a query naming a group is consistently false.
		if ( '' !== $group ) {
			return false;
		}

		return false !== \wp_next_scheduled( $hook, $args );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function get_next_scheduled( string $hook, array $args = array(), string $group = '' ): ?int {
		// A grouped schedule can never exist on WP-Cron, so a query naming a group has no next run.
		if ( '' !== $group ) {
			return null;
		}

		$next = \wp_next_scheduled( $hook, $args );
		return \is_int( $next ) ? $next : null;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Registers the 'cron_schedules' filter so a synthetic schedule resolves on a request that
	 * never calls a schedule method — wp-cron itself — letting WordPress reschedule a recurring
	 * event stored on an earlier request. The filter callback rebuilds the interval set from the
	 * cron array, so the synthetic schedule resolves even though the registry is per-request.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function register_lifecycle(): void {
		$this->ensure_filter_registered();
	}

	// endregion

	// region HOOKS

	/**
	 * Injects a synthetic 'dws_every_{N}s' schedule for every active interval.
	 *
	 * Filter callback for 'cron_schedules'. The active set unions intervals scheduled this
	 * request with intervals rebuilt from recurring events already in the cron array, so the
	 * synthetic schedule resolves even on a request whose backend instance never scheduled it.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<string, array{interval: int, display: string}> $schedules Existing schedules from WordPress.
	 *
	 * @return  array<string, array{interval: int, display: string}>
	 */
	public function register_synthetic_schedules( array $schedules ): array {
		foreach ( $this->active_intervals() as $interval ) {
			$schedules[ $this->schedule_name( $interval ) ] = array(
				'interval' => $interval,
				'display'  => \sprintf(
					/* translators: %d: interval in seconds. */
					\__( 'Every %d seconds', 'wp-framework-utilities' ),
					$interval
				),
			);
		}

		return $schedules;
	}

	// endregion

	// region HELPERS

	/**
	 * Rejects a non-empty group, which WordPress cron cannot honor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $group Requested grouping label.
	 *
	 * @return  Failure<SchedulingError>|null A failure when the group is non-empty, null when it is empty.
	 */
	protected function reject_group( string $group ): ?Failure {
		if ( '' === $group ) {
			return null;
		}

		$message = 'WordPress cron does not support action groups.';
		$this->logger?->error( $message, array( 'group' => $group ) );

		return Failure::from(
			new SchedulingError(
				SchedulingErrorReason::UnsupportedGroup,
				$message,
				array( 'group' => $group ),
			),
		);
	}

	/**
	 * Records an interval and ensures the schedule filter is wired, returning the interval's name.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $interval Interval in seconds.
	 *
	 * @return  string
	 */
	protected function ensure_schedule( int $interval ): string {
		$this->registered_intervals[ $interval ] = true;
		$this->scheduled_intervals               = null;
		$this->ensure_filter_registered();

		return $this->schedule_name( $interval );
	}

	/**
	 * Wires the 'cron_schedules' filter at most once per request via a stable [object, method] pair —
	 * a fresh closure, which WordPress cannot de-duplicate, would stack a new listener on every wiring.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	protected function ensure_filter_registered(): void {
		if ( $this->schedules_filter_registered ) {
			return;
		}

		\add_filter( 'cron_schedules', array( $this, 'register_synthetic_schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected -- synthetic intervals are positive ints validated before scheduling.
		$this->schedules_filter_registered = true;
	}

	/**
	 * Builds the synthetic schedule name for an interval.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $interval Interval in seconds.
	 *
	 * @return  string
	 */
	protected function schedule_name( int $interval ): string {
		return 'dws_every_' . $interval . 's';
	}

	/**
	 * The intervals needing a synthetic schedule this request: those scheduled this request,
	 * unioned with those rebuilt from recurring events already stored in the cron array.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  list<int>
	 */
	protected function active_intervals(): array {
		$intervals = $this->registered_intervals;
		foreach ( $this->scheduled_intervals() as $interval ) {
			$intervals[ $interval ] = true;
		}

		return \array_keys( $intervals );
	}

	/**
	 * Reads the intervals of synthetic 'dws_every_{N}s' events already stored in the cron array,
	 * so a recurring event scheduled on an earlier request keeps a resolvable schedule.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  list<int>
	 */
	protected function scheduled_intervals(): array {
		if ( null !== $this->scheduled_intervals ) {
			return $this->scheduled_intervals;
		}

		$intervals = array();
		foreach ( \_get_cron_array() as $hooks ) {
			foreach ( $hooks as $events ) {
				if ( ! \is_array( $events ) ) {
					continue;
				}
				foreach ( $events as $event ) {
					$schedule = \is_array( $event ) ? ( $event['schedule'] ?? null ) : null;
					if ( \is_string( $schedule ) && 1 === \preg_match( '/^dws_every_(\d+)s$/', $schedule, $matches ) ) {
						$intervals[] = (int) $matches[1];
					}
				}
			}
		}

		$this->scheduled_intervals = $intervals;
		return $this->scheduled_intervals;
	}

	/**
	 * Maps a WordPress cron scheduling return to a result — false or a WP_Error is a failure.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   bool|WP_Error $scheduled Return of wp_schedule_event / wp_schedule_single_event.
	 * @param   string        $hook      Hook that was being scheduled, for the error context.
	 *
	 * @return  Success<true>|Failure<SchedulingError> Success, or a failure on a false / WP_Error return.
	 */
	protected function result_for_schedule( bool|WP_Error $scheduled, string $hook ): AbstractResult {
		if ( true === $scheduled ) {
			return Success::from( true );
		}

		$context = array( 'hook' => $hook );
		if ( $scheduled instanceof WP_Error ) {
			$context['wp_error'] = $scheduled->get_error_message();
		}

		$message = 'WordPress cron rejected the scheduling request.';
		$this->logger?->error( $message, $context );

		return Failure::from(
			new SchedulingError( SchedulingErrorReason::ScheduleFailed, $message, $context ),
		);
	}

	/**
	 * Maps a WordPress cron clear return to a result — an int count is a success, a WP_Error (or any non-int) a failure.
	 *
	 * With $wp_error = true, wp_clear_scheduled_hook returns the count of unscheduled events (zero when none
	 * matched) on success, or a WP_Error when one or more events failed to unschedule; a count of zero is not a
	 * failure, so only a non-int return surfaces as one.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int|WP_Error $cleared Return of wp_clear_scheduled_hook with $wp_error = true.
	 * @param   string       $hook    Hook that was being unscheduled, for the error context.
	 *
	 * @return  Success<true>|Failure<SchedulingError> Success on an int count, a failure on a WP_Error / non-int return.
	 */
	protected function result_for_unschedule( int|WP_Error $cleared, string $hook ): AbstractResult {
		if ( \is_int( $cleared ) ) {
			return Success::from( true );
		}

		$context = array(
			'hook'     => $hook,
			'wp_error' => $cleared->get_error_message(),
		);

		$message = 'WordPress cron failed to clear the scheduled hook.';
		$this->logger?->error( $message, $context );

		return Failure::from(
			new SchedulingError( SchedulingErrorReason::ScheduleFailed, $message, $context ),
		);
	}

	// endregion
}
