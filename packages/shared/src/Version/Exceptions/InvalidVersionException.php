<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Shared\Version\Exceptions;

use DeepWebSolutions\Framework\Shared\Exception\AbstractInvalidArgumentException;

/**
 * Thrown when a string cannot be parsed into a valid Version value object.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class InvalidVersionException extends AbstractInvalidArgumentException {}
