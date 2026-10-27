<?php declare( strict_types=1 );
/**
 * Plugin Name:       Acme Persona Core
 * Description:       Adds a greeting shortcode and stores its settings by a migration, built on the core package alone.
 * Version:           1.0.0
 * Requires at least: 7.1
 * Requires PHP:      8.5
 * Author:            Acme
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       acme-persona-core
 *
 * @package Acme\PersonaCore
 */

use Acme\PersonaCore\Scoped\DeepWebSolutions\Framework\Bootstrap\Requirements;

defined( 'ABSPATH' ) || exit;

define( 'ACME_PC_FILE', __FILE__ );

$acme_pc_bootstrap = __DIR__ . '/vendor-prefixed/ahegyes/wp-framework-bootstrap/load.php';
if ( ! is_readable( $acme_pc_bootstrap ) ) { // The floor gate lives in the bootstrap package, so nothing built may load without it.
	return;
}

require_once $acme_pc_bootstrap;
if ( ! Requirements::check_floors( __FILE__ ) ) {
	return;
}

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/functions.php';
