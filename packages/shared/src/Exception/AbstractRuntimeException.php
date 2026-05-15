<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Shared\Exception;

/**
 * Base class for DWS framework runtime exceptions.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
abstract class AbstractRuntimeException extends \RuntimeException implements ExceptionInterface {}
