<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Shared\Exception;

/**
 * An exception thrown for a failure that only shows at runtime.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
class RuntimeException extends \RuntimeException implements ExceptionInterface {}
