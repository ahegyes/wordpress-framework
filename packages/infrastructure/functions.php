<?php declare( strict_types=1 );
/**
 * Aggregates this package's nested src/<namespace>/functions.php files. Composer autoloads only this file.
 *
 * @since   2.0.0
 * @version 2.0.0
 */

require_once __DIR__ . '/src/Settings/Schema/functions.php';
require_once __DIR__ . '/src/Utilities/AdminNotices/functions.php';
require_once __DIR__ . '/src/Utilities/Scheduling/functions.php';
