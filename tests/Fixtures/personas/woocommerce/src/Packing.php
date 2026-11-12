<?php declare( strict_types=1 );

namespace Acme\PersonaWooCommerce;

use Acme\PersonaWooCommerce\Scoped\DeepWebSolutions\Framework\WooCommerce\ProductData\ProductDataTabDefinitionInterface;
use Acme\PersonaWooCommerce\Scoped\DeepWebSolutions\Framework\WooCommerce\Setting\SectionFieldsDefinitionInterface;

\defined( 'ABSPATH' ) || exit;

/**
 * Defines the plugin's packing fields: the store's default box on its settings tab, and each product's box on its product data tab.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final class Packing implements ProductDataTabDefinitionInterface, SectionFieldsDefinitionInterface {
	// region FIELDS AND CONSTANTS

	/**
	 * The plugin's slug, which is also its text domain and, in snake case, the root of its hook names.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     string
	 */
	public const string SLUG = 'acme-persona-woocommerce';

	/**
	 * The prefix of the plugin's tabs, options and meta keys.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     string
	 */
	public const string PREFIX = 'acme_pw';

	// endregion

	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	#[\Override]
	public function get_section_fields( string $tab_id, string $section_id ): array {
		return array(
			array(
				'type'  => 'text',
				'id'    => self::PREFIX . '_default_box',
				'title' => __( 'Default box', 'acme-persona-woocommerce' ),
			),
			array(
				'type'  => 'checkbox',
				'id'    => ACME_PW_OPTION_DELETE_DATA,
				'title' => __( 'Data on uninstall', 'acme-persona-woocommerce' ),
				'desc'  => __( 'Delete the packing data when the plugin is uninstalled', 'acme-persona-woocommerce' ),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	#[\Override]
	public function get_product_data_tab( string $tab_id, \WC_Product $product ): array {
		return array(
			'label'  => __( 'Packing', 'acme-persona-woocommerce' ),
			'groups' => array(
				array(
					'fields' => array(
						array(
							'type'  => 'text',
							'id'    => ACME_PW_META_BOX,
							'label' => __( 'Box', 'acme-persona-woocommerce' ),
						),
					),
				),
			),
		);
	}

	// endregion
}
