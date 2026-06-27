<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Scheduling\Errors;

use DeepWebSolutions\Framework\Shared\Error\ErrorInterface;
use DeepWebSolutions\Framework\Utilities\Scheduling\SchedulingErrorReason;

/**
 * Failure payload for a scheduling mutation, carried by a {@see \DeepWebSolutions\Framework\Shared\Result\Failure}.
 *
 * Pairs a machine-readable {@see SchedulingErrorReason} with a human-readable message
 * and optional context (e.g. the offending interval, or a backend's own error text).
 * The reason is the branch key; the message and context are for logging and display.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final readonly class SchedulingError implements ErrorInterface {
	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   SchedulingErrorReason $reason  Machine-readable cause of the failure.
	 * @param   string                $message Human-readable description of the failure.
	 * @param   array<string, mixed>  $context Optional structured detail about the failure.
	 */
	public function __construct(
		public SchedulingErrorReason $reason,
		public string $message,
		public array $context = array(),
	) {}

	// endregion
}
