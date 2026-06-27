<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Schema\Exceptions;

use DeepWebSolutions\Framework\Shared\Exception\AbstractRuntimeException;

/**
 * Thrown when a settings section cannot be faithfully and safely exposed via REST under section-grouped storage.
 *
 * A backend that persists a whole section in one row exposes that row as a single REST setting, which the
 * WordPress settings endpoint gates by one fixed capability. The representation is faithful only when every
 * field of the section opts in, every opted-in field is a built-in type the framework can generate a schema
 * for, and the access intent matches that fixed capability — the page requires exactly it and no field
 * narrows access with its own capability, since a finer or different intent could not be honored.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class UnsupportedRestExposureException extends AbstractRuntimeException {}
