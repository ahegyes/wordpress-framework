<?php declare( strict_types=1 );
/**
 * Registers the plugin's settings tab and product data tab once WooCommerce is loaded at the plugin's floor.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @package Acme\PersonaWooCommerce
 */

use Acme\PersonaWooCommerce\Packing;
use Acme\PersonaWooCommerce\Scoped\DeepWebSolutions\Framework\WooCommerce\Logging\WooCommerceLogger;
use Acme\PersonaWooCommerce\Scoped\DeepWebSolutions\Framework\WooCommerce\ProductData\ProductDataTab;
use Acme\PersonaWooCommerce\Scoped\DeepWebSolutions\Framework\WooCommerce\Setting\SettingsTab;
use Acme\PersonaWooCommerce\Scoped\DeepWebSolutions\Framework\WooCommerce\WooCommerceRequirement;

defined( 'ABSPATH' ) || exit;

add_action( 'plugins_loaded', 'acme_pw_register_hooks', 15 );

/**
 * Registers the hooks of the plugin's tabs, unless WooCommerce is missing or older than the plugin's floor.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @return  void
 */
function acme_pw_register_hooks(): void {
	if ( ! new WooCommerceRequirement( ACME_PW_FILE )->check() ) {
		return;
	}

	new ProductDataTab( Packing::PREFIX, new Packing() )->register_hooks();
	add_filter( 'woocommerce_get_settings_pages', 'acme_pw_add_settings_tab' );
	add_action( 'woocommerce_update_options_' . Packing::PREFIX, 'acme_pw_log_settings_save' );
}

/**
 * Adds the plugin's settings tab, which WooCommerce's settings page class must exist to construct.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param   array<int, \WC_Settings_Page> $pages The settings tabs.
 *
 * @return  array<int, \WC_Settings_Page>
 */
function acme_pw_add_settings_tab( array $pages ): array {
	$pages[] = new SettingsTab(
		Packing::PREFIX,
		array(
			'label'    => __( 'Packing', 'acme-persona-woocommerce' ),
			'sections' => array( '' => __( 'General', 'acme-persona-woocommerce' ) ),
		),
		new Packing()
	);

	return $pages;
}

/**
 * Writes the save of the plugin's settings tab to WooCommerce's log.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @return  void
 */
function acme_pw_log_settings_save(): void {
	new WooCommerceLogger( 'acme-persona-woocommerce', 'info' )->info( 'The packing settings were saved.' );
}
