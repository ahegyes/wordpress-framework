<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Shared\Exception;

/**
 * Marks every exception the framework throws, so a caller can tell the framework's failures apart.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
interface ExceptionInterface extends \Throwable {}
