<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Conditionals\Exceptions;

use DeepWebSolutions\Framework\Shared\Exception\AbstractInvalidArgumentException;

/**
 * Thrown when a conditional is constructed with invalid configuration, such as a malformed
 * byte-shorthand minimum.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class InvalidConditionalConfigurationException extends AbstractInvalidArgumentException {}
