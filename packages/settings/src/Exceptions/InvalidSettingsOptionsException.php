<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Exceptions;

use DeepWebSolutions\Framework\Shared\Exception\AbstractRuntimeException;

/**
 * Thrown when a field's options source resolves to something other than an array.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class InvalidSettingsOptionsException extends AbstractRuntimeException {}
