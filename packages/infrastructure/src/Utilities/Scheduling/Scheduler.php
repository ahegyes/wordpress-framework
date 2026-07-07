<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Scheduling;

use DeepWebSolutions\Framework\Shared\Result\AbstractResult;
use DeepWebSolutions\Framework\Shared\Result\Success;
use DeepWebSolutions\Framework\Utilities\Scheduling\Backends\WPCronBackend;
use DeepWebSolutions\Framework\Utilities\Scheduling\Exceptions\InvalidSchedulerConfigurationException;

/**
 * Backend-agnostic scheduling facade over an ordered list of backends.
 *
 * Holds one or more backends in declaration order, each reporting its own readiness via
 * {@see SchedulerBackendInterface::is_ready()}. A schedule write targets the first ready
 * backend, falling back to the last backend when none is ready. The read and clear surface
 * spans every ready backend, so a job scheduled while a preferred backend was not ready
 * remains visible and cancellable after the preference changes. The consumer states its
 * backends: an omitted argument yields the WordPress-cron baseline alone, and a consumer
 * preferring Action Scheduler passes it explicitly, first.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final readonly class Scheduler implements SchedulerBackendInterface {
	// region FIELDS AND CONSTANTS

	/**
	 * Backends in declaration order: write preference, clear/query consultation, and failure precedence.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     non-empty-list<SchedulerBackendInterface>
	 */
	protected array $backends;

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<SchedulerBackendInterface> $backends Backends in preference order (values re-indexed, keys ignored); must not be empty. An omitted argument yields the WordPress-cron baseline backend.
	 *
	 * @throws  InvalidSchedulerConfigurationException When $backends is empty.
	 */
	public function __construct( array $backends = array( new WPCronBackend() ) ) {
		if ( array() === $backends ) {
			throw new InvalidSchedulerConfigurationException( 'Scheduler requires at least one backend.' );
		}

		$this->backends = \array_values( $backends );
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
		return $this->write_backend()->schedule_recurring( $hook, $interval, $args, $first_run_timestamp, $group );
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
		return $this->write_backend()->schedule_single( $hook, $timestamp, $args, $group );
	}

	/**
	 * {@inheritDoc}
	 *
	 * Consults every ready backend; the first failure in declaration order wins. A backend
	 * that is not ready is unreachable, so clears report the outcome of the ready backends
	 * alone — none ready is a vacuous success.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	#[\NoDiscard( 'a scheduling failure must be handled, not dropped' )]
	public function unschedule( string $hook, array $args = array(), string $group = '' ): AbstractResult {
		$results = array();
		foreach ( $this->ready_backends() as $backend ) {
			$results[] = $backend->unschedule( $hook, $args, $group );
		}

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
		foreach ( $this->ready_backends() as $backend ) {
			if ( $backend->is_scheduled( $hook, $args, $group ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function get_next_scheduled( string $hook, array $args = array(), string $group = '' ): ?int {
		$timestamps = array();
		foreach ( $this->ready_backends() as $backend ) {
			$next = $backend->get_next_scheduled( $hook, $args, $group );
			if ( \is_int( $next ) ) {
				$timestamps[] = $next;
			}
		}

		return array() === $timestamps ? null : \min( $timestamps );
	}

	/**
	 * {@inheritDoc}
	 *
	 * The facade is ready as soon as any backend is; writes stay accepted regardless via the
	 * last-backend fallback.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function is_ready(): bool {
		return array() !== $this->ready_backends();
	}

	/**
	 * {@inheritDoc}
	 *
	 * Wires every backend unconditionally, ready or not, so a backend that becomes ready
	 * later in the request — or handles jobs scheduled on an earlier request — is prepared.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function register_hooks(): void {
		foreach ( $this->backends as $backend ) {
			$backend->register_hooks();
		}
	}

	// endregion

	// region HELPERS

	/**
	 * Selects the backend for a schedule write: the first ready backend, or the last backend
	 * when none is ready — a write must land somewhere, and the last backend is the wiring's
	 * final fallback.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  SchedulerBackendInterface
	 */
	protected function write_backend(): SchedulerBackendInterface {
		foreach ( $this->backends as $backend ) {
			if ( $backend->is_ready() ) {
				return $backend;
			}
		}

		return $this->backends[ \count( $this->backends ) - 1 ];
	}

	/**
	 * The backends consultable for the current clear or query call, in declaration order.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  list<SchedulerBackendInterface>
	 */
	protected function ready_backends(): array {
		$ready = array();
		foreach ( $this->backends as $backend ) {
			if ( $backend->is_ready() ) {
				$ready[] = $backend;
			}
		}

		return $ready;
	}

	// endregion
}
