<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Exceptions;

use DeepWebSolutions\Framework\Shared\Exception\AbstractInvalidArgumentException;

/**
 * Thrown when a settings field identifier is invalid: malformed against the id charset at descriptor
 * construction, or addressed against a page on which it is not registered.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class InvalidSettingsFieldException extends AbstractInvalidArgumentException {}
