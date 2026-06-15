<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Exceptions;

use DeepWebSolutions\Framework\Shared\Exception\AbstractRuntimeException;

/**
 * Thrown when two sections registered on the same settings page share an id.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class DuplicateSettingsSectionException extends AbstractRuntimeException {}
