<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Exceptions;

use DeepWebSolutions\Framework\Shared\Exception\AbstractInvalidArgumentException;

/**
 * Thrown when a constructor-supplied string reused as a WordPress-global name — an option key,
 * cache group, or hook-name segment — does not match the global-name-prefix charset.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class InvalidGlobalNamePrefixException extends AbstractInvalidArgumentException {}
