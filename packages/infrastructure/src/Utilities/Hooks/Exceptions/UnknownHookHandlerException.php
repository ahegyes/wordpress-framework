<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Hooks\Exceptions;

use DeepWebSolutions\Framework\Shared\Exception\AbstractRuntimeException;

/**
 * Thrown when a hook registration call names a handler ID no handler is registered under.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class UnknownHookHandlerException extends AbstractRuntimeException {}
