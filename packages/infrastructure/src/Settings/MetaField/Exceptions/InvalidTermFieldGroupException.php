<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\MetaField\Exceptions;

use DeepWebSolutions\Framework\Shared\Exception\AbstractInvalidArgumentException;

/**
 * Thrown when a term field group is constructed with a taxonomy outside WordPress's taxonomy-key rules,
 * which is interpolated into the term hook names.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class InvalidTermFieldGroupException extends AbstractInvalidArgumentException {}
