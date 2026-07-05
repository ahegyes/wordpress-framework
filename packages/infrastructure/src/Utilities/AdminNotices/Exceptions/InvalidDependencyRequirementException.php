<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\AdminNotices\Exceptions;

use DeepWebSolutions\Framework\Shared\Exception\AbstractInvalidArgumentException;

/**
 * Thrown when a dependency-requirement descriptor declares a notice id that is not a stable
 * dismissal key.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class InvalidDependencyRequirementException extends AbstractInvalidArgumentException {}
