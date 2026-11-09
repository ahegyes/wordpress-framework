<?php declare( strict_types=1 );
/**
 * Loads the classes a plugin's entry file calls before any autoloader, and returns false when a file they need is missing.
 *
 * PHP 7.4 cannot catch a failed require, so the files are checked before any is required.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\Framework\Bootstrap
 */

namespace DeepWebSolutions\Framework\Bootstrap;

\defined( 'ABSPATH' ) || exit;

if ( ! \is_readable( __DIR__ . '/src/Requirements.php' ) || ! \is_readable( __DIR__ . '/src/Updater.php' ) ) {
	return false;
}

require_once __DIR__ . '/src/Requirements.php';
require_once __DIR__ . '/src/Updater.php';

return true;
