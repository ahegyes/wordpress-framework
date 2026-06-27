<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\MetaField\Exceptions;

use DeepWebSolutions\Framework\Shared\Exception\AbstractInvalidArgumentException;

/**
 * Thrown when a field group is constructed with an id outside the id charset, which is used as a
 * form-field-name segment, a nonce key, and an element identifier.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class InvalidFieldGroupException extends AbstractInvalidArgumentException {}
