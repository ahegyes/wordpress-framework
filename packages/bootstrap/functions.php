<?php
/**
 * Pre-autoload aggregator for the bootstrap package. Requires the per-concern
 * function files below. Composer autoloads only this file via autoload-files.
 *
 * @since   2.0.0
 * @version 2.0.0
 */

require_once __DIR__ . '/src/Environment/functions.php';
require_once __DIR__ . '/src/Plugin/functions.php';
require_once __DIR__ . '/src/Notice/functions.php';
require_once __DIR__ . '/src/Requirements/functions.php';
