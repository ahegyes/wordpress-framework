<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Shared\Exception;

/**
 * An exception thrown for a developer error that a code change fixes.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
class LogicException extends \LogicException implements ExceptionInterface {}
