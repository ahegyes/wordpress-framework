<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Shared\Exception;

use DeepWebSolutions\Framework\Shared\Error\ErrorInterface;

/**
 * Marker for unexpected failures — infrastructure outages, programmer errors,
 * runtime conditions the caller shouldn't recover from at this level.
 *
 * Contrast with {@see ErrorInterface}: errors represent expected failure outcomes
 * (domain-rule violations, validation failures) handled as data via Result.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
interface ExceptionInterface extends \Throwable {}
