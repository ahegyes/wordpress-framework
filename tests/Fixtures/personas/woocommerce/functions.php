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

add_action(
	'plugins_loaded',
	static function (): void {
		if ( ! WooCommerceRequirement::check( ACME_PW_FILE ) ) {
			return;
		}

		new ProductDataTab( Packing::SLUG, Packing::PREFIX, new Packing() )->register_hooks();
		add_filter(
			'woocommerce_get_settings_pages',
			static function ( array $pages ): array {
				// WooCommerce's settings page class, which the tab extends, exists only once this filter runs.
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
		);
		add_action(
			'woocommerce_update_options_' . Packing::PREFIX,
			static function (): void {
				new WooCommerceLogger( 'acme-persona-woocommerce', 'info' )->info( 'The packing settings were saved.' );
			}
		);
	},
	15
);
