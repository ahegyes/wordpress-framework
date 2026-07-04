<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\AdminNotices\Exceptions;

use DeepWebSolutions\Framework\Shared\ValueObject\Exceptions\InvalidValueObjectException;

/**
 * Thrown when an admin notice declares an id that is not a stable dismissal key.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class InvalidAdminNoticeException extends InvalidValueObjectException {
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
		get => 'AdminNotice';
	}
}
