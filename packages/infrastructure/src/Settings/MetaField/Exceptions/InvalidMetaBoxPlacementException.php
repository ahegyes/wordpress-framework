<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\MetaField\Exceptions;

use DeepWebSolutions\Framework\Shared\ValueObject\Exceptions\InvalidValueObjectException;

/**
 * Thrown when a meta-box placement is constructed with a malformed screen or a context or priority
 * outside WordPress' closed sets.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class InvalidMetaBoxPlacementException extends InvalidValueObjectException {
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
		get => 'MetaBoxPlacement';
	}
}
