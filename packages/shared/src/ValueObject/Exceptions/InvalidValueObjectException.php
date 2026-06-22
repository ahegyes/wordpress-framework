<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Shared\ValueObject\Exceptions;

use DeepWebSolutions\Framework\Shared\Exception\AbstractInvalidArgumentException;

/**
 * Base class for exceptions raised when a value object invariant is violated.
 *
 * Each value object's invalidity exception extends this and supplies its
 * `value_object_type`, so the family shares one message format and a single
 * catch point (`catch ( InvalidValueObjectException )`).
 *
 * @since   2.0.0
 * @version 2.0.0
 */
abstract class InvalidValueObjectException extends AbstractInvalidArgumentException {
	/**
	 * Owning value-object class name.
	 *
	 * PHP 8.4+ property hook (`{ get; }`) — PHPCompatibility's curly-brace
	 * detector misreads it as removed PHP 7.4 array-access syntax.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	abstract protected string $value_object_type { get; } // phpcs:ignore PHPCompatibility.Syntax.RemovedCurlyBraceArrayAccess.Removed

	/**
	 * Builds the exception message from the reason.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string          $reason   Why the value object is invalid.
	 * @param   int             $code     Exception code.
	 * @param   \Throwable|null $previous Previous exception for chaining.
	 */
	public function __construct( string $reason, int $code = 0, ?\Throwable $previous = null ) {
		$message = \sprintf( 'Value object of type `%s` is invalid for the following reason: %s', $this->value_object_type, $reason );
		parent::__construct( $message, $code, $previous );
	}
}
