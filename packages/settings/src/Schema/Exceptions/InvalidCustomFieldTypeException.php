<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Schema\Exceptions;

use DeepWebSolutions\Framework\Shared\Exception\AbstractInvalidArgumentException;

/**
 * Thrown when a custom field type declares a type token outside the identifier charset.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class InvalidCustomFieldTypeException extends AbstractInvalidArgumentException {}
