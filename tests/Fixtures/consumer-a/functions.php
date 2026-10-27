<?php declare( strict_types=1 );
/**
 * Defines the plugin's accessor, boots it on plugins_loaded and declares its WooCommerce feature compatibility.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @package Acme\ConsumerA
 */

use Acme\ConsumerA\Plugin;
use Automattic\WooCommerce\Utilities\FeaturesUtil;

defined( 'ABSPATH' ) || exit;

add_action( 'plugins_loaded', 'acme_ca_boot', 15 );
add_action( 'before_woocommerce_init', 'acme_ca_declare_compatibility' );

/**
 * Returns the plugin's composition root, built on first use.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @return  Plugin
 */
function acme_ca_plugin(): Plugin {
	static $plugin = null;
	if ( ! $plugin instanceof Plugin ) {
		$plugin = new Plugin();
	}

	return $plugin;
}

/**
 * Boots the plugin, reporting a composition root that cannot be built as an incomplete build.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @return  void
 */
function acme_ca_boot(): void {
	try {
		acme_ca_plugin()->boot();
	} catch ( Throwable $error ) {
		acme_ca_handle_build_error( $error );
	}
}

/**
 * Declares that the plugin works with WooCommerce's order tables.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @return  void
 */
function acme_ca_declare_compatibility(): void {
	FeaturesUtil::declare_compatibility( 'custom_order_tables', ACME_CA_FILE, true );
}
