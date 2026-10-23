<?php declare( strict_types=1 );
/**
 * Loads the classes a plugin's uninstall file needs.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\Framework\Bootstrap
 */

namespace DeepWebSolutions\Framework\Bootstrap;

\defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/src/Updater.php';
require_once __DIR__ . '/src/Uninstaller.php';
