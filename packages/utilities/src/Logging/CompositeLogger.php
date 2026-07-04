<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Logging;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

/**
 * PSR-3 logger that forwards each record to multiple concrete loggers.
 *
 * Use when one failure must reach separate sinks, such as an operator-facing admin notice and a
 * diagnostic file logger. The same message and context are passed to each logger in construction order.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class CompositeLogger implements LoggerInterface {
	// region TRAITS

	use LoggerTrait;

	// endregion

	// region FIELDS AND CONSTANTS

	/**
	 * Loggers that receive every record.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     list<LoggerInterface>
	 */
	protected array $loggers;

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   LoggerInterface ...$loggers Loggers that receive every record.
	 */
	public function __construct( LoggerInterface ...$loggers ) {
		$this->loggers = \array_values( $loggers );
	}

	// endregion

	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * Delivery to every sink precedes failure propagation: each logger receives the record even
	 * when an earlier one throws, and the first caught failure is rethrown once the record has
	 * reached all of them — a throwing diagnostic sink cannot starve the notice sink, nor the
	 * reverse.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed                   $level   Log level.
	 * @param   string|\Stringable      $message Log message.
	 * @param   array<array-key, mixed> $context Log context.
	 *
	 * @throws  \Throwable The first failure thrown by a delegate logger.
	 */
	#[\Override]
	public function log( $level, string|\Stringable $message, array $context = array() ): void {
		$first_failure = null;

		foreach ( $this->loggers as $logger ) {
			try {
				$logger->log( $level, $message, $context );
			} catch ( \Throwable $failure ) {
				$first_failure ??= $failure;
			}
		}

		if ( null !== $first_failure ) {
			throw $first_failure;
		}
	}

	// endregion
}
