<?php declare( strict_types=1 );
/**
 * Plugin Name:          Acme Consumer A
 * Description:          Adds a product data tab and an order-count export to WooCommerce, built on every framework package.
 * Version:              1.0.0
 * Requires at least:    7.1
 * Requires PHP:         8.5
 * Requires Plugins:     woocommerce
 * WC requires at least: 11.2
 * WC tested up to:      11.2
 * Author:               Acme
 * License:              GPL-3.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-3.0.html
 * Update URI:           https://github.com/acme/acme-consumer-a
 * Text Domain:          acme-consumer-a
 * Domain Path:          /languages
 *
 * @package Acme\ConsumerA
 */

use Acme\ConsumerA\Scoped\DeepWebSolutions\Framework\Bootstrap\Requirements;
use Acme\ConsumerA\Scoped\DeepWebSolutions\Framework\Bootstrap\Updater;

defined( 'ABSPATH' ) || exit;

define( 'ACME_CA_FILE', __FILE__ );
load_plugin_textdomain( 'acme-consumer-a', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' ); // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- WordPress registers languages/ itself only for plugins it does not network-activate.

$acme_ca_bootstrap = __DIR__ . '/vendor-prefixed/ahegyes/wp-framework-bootstrap/load.php';
if ( is_readable( $acme_ca_bootstrap ) ) {
	require_once $acme_ca_bootstrap;
	Updater::register( __FILE__ ); // Before the floor gate, so a release that lowers the floors still reaches a site below them.
	if ( ! Requirements::check_floors( __FILE__ ) ) {
		return;
	}
}

require_once __DIR__ . '/includes/safeguards.php';

if ( ! is_readable( $acme_ca_bootstrap ) ) { // The floor gate never ran, so nothing built may load.
	add_action( 'all_admin_notices', 'acme_ca_render_build_notice' );
	return;
}

try {
	require_once __DIR__ . '/vendor/autoload.php';
	require_once __DIR__ . '/functions.php';
} catch ( Throwable $error ) { // PHP throws an Error for a missing required file, so a partial deploy lands here.
	acme_ca_handle_build_error( $error );
	return;
}

/**
 * Reports a build that could not load, rethrowing in local and development environments and otherwise logging the error and showing the build notice.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param   Throwable $error The error that stopped the build.
 *
 * @throws  Throwable Thrown in local and development environments.
 *
 * @return  void
 */
function acme_ca_handle_build_error( Throwable $error ): void {
	if ( in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) {
		throw $error;
	}

	error_log( 'Acme Consumer A did not finish loading: ' . $error->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- The plugin's logger is part of the build that failed.
	add_action( 'all_admin_notices', 'acme_ca_render_build_notice' );
}

/**
 * Prints the notice that the plugin's build is incomplete to users who can activate plugins.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @return  void
 */
function acme_ca_render_build_notice(): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html__( 'Acme Consumer A is missing some of its files, so its features are off. Reinstall the plugin to restore them.', 'acme-consumer-a' ) );
}
