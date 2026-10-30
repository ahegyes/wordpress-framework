<?php declare( strict_types=1 );
/**
 * Plugin Name:       Acme Consumer B
 * Description:       Adds a settings page, built on every framework package but woocommerce.
 * Version:           1.0.0
 * Requires at least: 7.1
 * Requires PHP:      8.5
 * Author:            Acme
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Update URI:        https://github.com/acme/acme-consumer-b
 * Text Domain:       acme-consumer-b
 *
 * @package Acme\ConsumerB
 */

use Acme\ConsumerB\Scoped\DeepWebSolutions\Framework\Bootstrap\Requirements;
use Acme\ConsumerB\Scoped\DeepWebSolutions\Framework\Bootstrap\Updater;

defined( 'ABSPATH' ) || exit;

define( 'ACME_CB_FILE', __FILE__ );

$acme_cb_bootstrap = __DIR__ . '/vendor-prefixed/ahegyes/wp-framework-bootstrap/load.php';
if ( ! is_readable( $acme_cb_bootstrap ) ) { // The floor gate lives in the bootstrap package, so nothing built may load without it.
	return;
}

require_once $acme_cb_bootstrap;
Updater::register( __FILE__ ); // Before the floor gate, so a release that lowers the floors still reaches a site below them.
if ( ! Requirements::check_floors( __FILE__ ) ) {
	return;
}

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/functions.php';
