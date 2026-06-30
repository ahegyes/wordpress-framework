<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Logging;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

/**
 * PSR-3 logger decorator that removes marked sensitive spans before delegating.
 *
 * Messages and string context values may wrap secrets in <sensitive>...</sensitive>; the default
 * redacting mode drops those spans so downstream loggers can be wired without duplicating the
 * stripping rule. Redaction is intentionally shallow for non-string context values because PSR-3
 * context can carry arbitrary objects such as Throwable instances.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class RedactingLogger implements LoggerInterface {
	// region TRAITS

	use LoggerTrait;

	// endregion

	// region FIELDS AND CONSTANTS

	/**
	 * Marker span used by framework consumers to wrap sensitive log fragments.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	protected const SENSITIVE_PATTERN = '#<sensitive>.*?</sensitive>#is';

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   LoggerInterface $inner  Logger that receives the sanitized record.
	 * @param   bool            $redact Whether sensitive spans are removed before delegation.
	 */
	public function __construct(
		protected LoggerInterface $inner,
		protected bool $redact = true,
	) {}

	// endregion

	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed                   $level   Log level.
	 * @param   string|\Stringable      $message Log message.
	 * @param   array<array-key, mixed> $context Log context.
	 */
	#[\Override]
	public function log( $level, string|\Stringable $message, array $context = array() ): void {
		if ( ! $this->redact ) {
			$this->inner->log( $level, $message, $context );
			return;
		}

		$this->inner->log(
			$level,
			$this->redact_string( (string) $message ),
			$this->redact_context( $context ),
		);
	}

	// endregion

	// region HELPERS

	/**
	 * Removes every sensitive marker span from a string.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $value Value to redact.
	 *
	 * @return  string
	 */
	protected function redact_string( string $value ): string {
		return \preg_replace( self::SENSITIVE_PATTERN, '', $value ) ?? $value;
	}

	/**
	 * Recursively redacts string context values while preserving non-string values as PSR-3 context data.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<array-key, mixed> $context Context to redact.
	 *
	 * @return  array<array-key, mixed>
	 */
	protected function redact_context( array $context ): array {
		foreach ( $context as $key => $value ) {
			$context[ $key ] = $this->redact_value( $value );
		}

		return $context;
	}

	/**
	 * Redacts one context value.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed $value Context value.
	 *
	 * @return  mixed
	 */
	protected function redact_value( mixed $value ): mixed {
		if ( \is_string( $value ) ) {
			return $this->redact_string( $value );
		}

		if ( \is_array( $value ) ) {
			return $this->redact_context( $value );
		}

		return $value;
	}

	// endregion
}
