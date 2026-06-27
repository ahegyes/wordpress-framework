<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\ObjectField\Exceptions;

use DeepWebSolutions\Framework\Shared\Exception\AbstractRuntimeException;

/**
 * Thrown when an object meta box is malformed or unsupported — a meta-box id outside the id charset
 * (it reaches WordPress meta-box markup unescaped) or a screen a backend does not support.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class InvalidObjectMetaBoxException extends AbstractRuntimeException {}
