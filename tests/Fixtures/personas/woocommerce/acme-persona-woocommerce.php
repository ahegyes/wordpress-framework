<?php declare( strict_types=1 );
/**
 * Plugin Name:          Acme Persona WooCommerce
 * Description:          Adds a WooCommerce settings tab and a product data tab through the woocommerce package, without the plugin kernel.
 * Version:              1.0.0
 * Requires at least:    7.1
 * Requires PHP:         8.5
 * Requires Plugins:     woocommerce
 * WC requires at least: 11.2
 * WC tested up to:      11.2
 * Author:               Acme
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          acme-persona-woocommerce
 *
 * @package Acme\PersonaWooCommerce
 */

use Acme\PersonaWooCommerce\Scoped\DeepWebSolutions\Framework\Bootstrap\Requirements;

defined( 'ABSPATH' ) || exit;

define( 'ACME_PW_FILE', __FILE__ );

$acme_pw_bootstrap = __DIR__ . '/vendor-prefixed/ahegyes/wp-framework-bootstrap/load.php';
if ( ! is_readable( $acme_pw_bootstrap ) ) { // The floor gate lives in the bootstrap package, so nothing built may load without it.
	return;
}

require_once $acme_pw_bootstrap;
if ( ! Requirements::check_floors( __FILE__ ) ) {
	return;
}

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/functions.php';
