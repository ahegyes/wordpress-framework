<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Shared\Version\Exceptions;

use DeepWebSolutions\Framework\Shared\ValueObject\Exceptions\InvalidValueObjectException;

/**
 * Thrown when a string cannot be parsed into a valid Version value object, or when
 * version parts cannot compose one.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class InvalidVersionException extends InvalidValueObjectException {
	/**
	 * Identifies the owning value object in invalidity messages.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	#[\Override]
	protected string $value_object_type { // phpcs:ignore PHPCompatibility.Syntax.RemovedCurlyBraceArrayAccess.Removed -- PHP 8.4 property hook, not array access.
		get => 'Version';
	}
}
