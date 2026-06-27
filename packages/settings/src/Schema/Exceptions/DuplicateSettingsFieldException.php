<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Schema\Exceptions;

use DeepWebSolutions\Framework\Shared\Exception\AbstractRuntimeException;

/**
 * Thrown when two contributed settings fields share the same identifier.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class DuplicateSettingsFieldException extends AbstractRuntimeException {}
