<?php declare( strict_types=1 );
/**
 * Registers the plugin's settings page on plugins_loaded.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @package Acme\PersonaSettings
 */

use Acme\PersonaSettings\Settings;
use Acme\PersonaSettings\Scoped\DeepWebSolutions\Framework\Settings\AdminPage\OptionsPage;

defined( 'ABSPATH' ) || exit;

add_action( 'plugins_loaded', 'acme_ps_register_settings_page', 15 );

/**
 * Registers the hooks of the plugin's settings page.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @return  void
 */
function acme_ps_register_settings_page(): void {
	new OptionsPage( Settings::PREFIX, new Settings() )->register_hooks();
}
