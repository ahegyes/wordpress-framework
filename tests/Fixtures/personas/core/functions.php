<?php declare( strict_types=1 );
/**
 * Defines the plugin's accessor, boots it on plugins_loaded and loads its migration steps.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @package Acme\PersonaCore
 */

use Acme\PersonaCore\Plugin;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/migrations.php';

add_action( 'plugins_loaded', 'acme_pc_boot', 15 );

/**
 * Returns the plugin's composition root, built on first use.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @return  Plugin
 */
function acme_pc_plugin(): Plugin {
	static $plugin = null;
	if ( ! $plugin instanceof Plugin ) {
		$plugin = new Plugin();
	}

	return $plugin;
}

/**
 * Boots the plugin.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @return  void
 */
function acme_pc_boot(): void {
	acme_pc_plugin()->boot();
}
