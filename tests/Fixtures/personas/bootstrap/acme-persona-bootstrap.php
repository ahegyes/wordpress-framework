<?php declare( strict_types=1 );
/**
 * Plugin Name:       Acme Persona Bootstrap
 * Description:       Shows an admin notice, gated on its floors and updated from GitHub by the bootstrap package alone.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Acme
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://github.com/acme/acme-persona-bootstrap
 * Text Domain:       acme-persona-bootstrap
 *
 * @package Acme\PersonaBootstrap
 */

use Acme\PersonaBootstrap\Scoped\DeepWebSolutions\Framework\Bootstrap\Requirements;
use Acme\PersonaBootstrap\Scoped\DeepWebSolutions\Framework\Bootstrap\Updater;

defined( 'ABSPATH' ) || exit;

$acme_pb_bootstrap = __DIR__ . '/vendor-prefixed/ahegyes/wp-framework-bootstrap/load.php';
if ( ! is_readable( $acme_pb_bootstrap ) ) { // The floor gate lives in the bootstrap package, so nothing built may load without it.
	return;
}

require_once $acme_pb_bootstrap;
Updater::register( __FILE__ ); // Before the floor gate, so a release that lowers the floors still reaches a site below them.
if ( ! Requirements::check_floors( __FILE__ ) ) {
	return;
}

require_once __DIR__ . '/functions.php';
