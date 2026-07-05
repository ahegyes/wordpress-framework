<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Schema\Exceptions;

use DeepWebSolutions\Framework\Shared\Exception\AbstractInvalidArgumentException;

/**
 * Thrown when a settings section identifier is malformed against the id charset at descriptor construction.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class InvalidSettingsSectionException extends AbstractInvalidArgumentException {}
