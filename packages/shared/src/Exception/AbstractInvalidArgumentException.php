<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Shared\Exception;

/**
 * Base class for DWS framework invalid-argument exceptions.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
abstract class AbstractInvalidArgumentException extends \InvalidArgumentException implements ExceptionInterface {}
