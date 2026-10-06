<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Shared\Exception;

/**
 * An exception thrown when an argument fails validation.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
class InvalidArgumentException extends \InvalidArgumentException implements ExceptionInterface {}
