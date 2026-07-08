<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\AdminNotices\Exceptions;

use DeepWebSolutions\Framework\Shared\Exception\AbstractRuntimeException;

/**
 * Thrown when a notice operation names a store no store is registered under.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class UnknownNoticeStoreException extends AbstractRuntimeException {}
