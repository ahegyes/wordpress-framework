<?php declare( strict_types=1 );
/**
 * Loads the plugin's safeguards, registers its updater and, once the site meets its floors, loads its autoloader and functions, showing a notice where its build is incomplete.
 *
 * Plugin Name:          Acme Consumer A
 * Description:          Adds a product data tab and an order-count export to WooCommerce, built on every framework package.
 * Version:              1.0.0
 * Requires at least:    7.1
 * Requires PHP:         8.5
 * Requires Plugins:     woocommerce
 * WC requires at least: 11.3
 * WC tested up to:      11.3
 * Author:               Acme
 * License:              GPL-3.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-3.0.html
 * Update URI:           https://github.com/acme/acme-consumer-a
 * Text Domain:          acme-consumer-a
 * Domain Path:          /languages
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @package Acme\ConsumerA
 */

use Acme\ConsumerA\Safeguards;
use Acme\ConsumerA\Scoped\DeepWebSolutions\Framework\Bootstrap\Requirements;
use Acme\ConsumerA\Scoped\DeepWebSolutions\Framework\Bootstrap\Updater;

defined( 'ABSPATH' ) || exit;

define( 'ACME_CA_FILE', __FILE__ );
load_plugin_textdomain( 'acme-consumer-a', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' ); // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- WordPress registers languages/ itself only for plugins it does not network-activate.

require_once __DIR__ . '/Safeguards.php'; // Before the floor gate, so the restrictions hold on any runtime that parses the plugin.
Safeguards::register();

try {
	$acme_ca_bootstrap = __DIR__ . '/vendor-prefixed/ahegyes/wp-framework-bootstrap/load.php';
	if ( ! is_readable( $acme_ca_bootstrap ) ) { // The floor gate lives in the bootstrap package, so nothing built may load without it.
		throw new RuntimeException( 'The bootstrap package is missing.' );
	}

	require_once $acme_ca_bootstrap;
	Updater::register( __FILE__ ); // Before the floor gate, so a release that lowers the floors still reaches a site below them.
	if ( ! Requirements::check_floors( __FILE__ ) ) {
		return;
	}

	require_once __DIR__ . '/vendor/autoload.php';
	require_once __DIR__ . '/functions.php';
} catch ( Throwable $error ) { // PHP throws an Error for a missing required file, so an incomplete build lands here.
	if ( function_exists( 'wp_get_environment_type' ) && in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) {
		throw $error;
	}

	add_action(
		'all_admin_notices',
		static function (): void {
			if ( current_user_can( 'activate_plugins' ) ) {
				printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html__( 'Acme Consumer A is missing some of its files, so its features are off. Reinstall the plugin to restore them.', 'acme-consumer-a' ) );
			}
		}
	);
}
