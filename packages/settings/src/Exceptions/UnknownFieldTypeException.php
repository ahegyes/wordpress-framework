<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Exceptions;

use DeepWebSolutions\Framework\Shared\Exception\AbstractRuntimeException;

/**
 * Thrown when a settings field declares a type outside the framework taxonomy.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class UnknownFieldTypeException extends AbstractRuntimeException {}
