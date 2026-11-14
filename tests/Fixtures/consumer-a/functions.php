<?php declare( strict_types=1 );
/**
 * Loads the files in includes/, defines the plugin's accessor, boots the plugin on plugins_loaded and declares its WooCommerce feature compatibility.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @package Acme\ConsumerA
 */

use Acme\ConsumerA\Plugin;
use Automattic\WooCommerce\Utilities\FeaturesUtil;

defined( 'ABSPATH' ) || exit;

$acme_ca_includes = glob( __DIR__ . '/includes/*.php' );
if ( false === $acme_ca_includes ) {
	throw new RuntimeException( 'The includes directory could not be read.' );
}

sort( $acme_ca_includes );
foreach ( $acme_ca_includes as $acme_ca_include ) {
	if ( ! str_starts_with( basename( $acme_ca_include ), '_' ) ) { // An underscore prefix keeps a file from loading.
		require_once $acme_ca_include;
	}
}

// The hooks come last, so an include that throws leaves nothing of the plugin hooked.
add_action(
	'plugins_loaded',
	static function (): void {
		acme_ca_plugin()->boot();
	},
	15
);
add_action(
	'before_woocommerce_init',
	static function (): void {
		FeaturesUtil::declare_compatibility( 'custom_order_tables', ACME_CA_FILE, true );
	}
);

/**
 * Returns the plugin's composition root, built on first use.
 *
 * Only the function is contract: the root's members stay internal unless tagged @api.
 *
 * @api
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
