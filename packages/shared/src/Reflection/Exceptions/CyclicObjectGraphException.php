<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Shared\Reflection\Exceptions;

use DeepWebSolutions\Framework\Shared\Exception\AbstractRuntimeException;

/**
 * Thrown when a cyclic object graph is detected during conversion to primitives:
 * a nested object re-entered while its own expansion is still in flight.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class CyclicObjectGraphException extends AbstractRuntimeException {}
