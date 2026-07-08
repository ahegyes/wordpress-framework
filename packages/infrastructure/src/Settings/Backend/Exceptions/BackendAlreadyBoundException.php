<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Backend\Exceptions;

use DeepWebSolutions\Framework\Shared\Exception\AbstractRuntimeException;

/**
 * Thrown when a page is registered on a settings backend instance already bound to a page.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class BackendAlreadyBoundException extends AbstractRuntimeException {}
