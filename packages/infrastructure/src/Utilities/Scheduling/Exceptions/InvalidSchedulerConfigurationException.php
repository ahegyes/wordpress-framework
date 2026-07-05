<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Scheduling\Exceptions;

use DeepWebSolutions\Framework\Shared\Exception\AbstractInvalidArgumentException;

/**
 * Thrown when a scheduler is constructed with invalid wiring, such as an empty backend list.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class InvalidSchedulerConfigurationException extends AbstractInvalidArgumentException {}
