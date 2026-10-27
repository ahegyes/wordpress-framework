<?php declare( strict_types=1 );

namespace Acme\ConsumerA;

use Acme\ConsumerA\Scoped\DeepWebSolutions\Framework\Core\ComponentInterface;
use Acme\ConsumerA\Scoped\DeepWebSolutions\Framework\WooCommerce\ProductData\ProductDataTab;
use Acme\ConsumerA\Scoped\DeepWebSolutions\Framework\WooCommerce\ProductData\ProductDataTabDefinitionInterface;

\defined( 'ABSPATH' ) || exit;

/**
 * Adds a tab to the product data panel that stores a packing note on each product.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final class ProductData implements ComponentInterface, ProductDataTabDefinitionInterface {
	// region FIELDS AND CONSTANTS

	/**
	 * The product meta key of the packing note.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     string
	 */
	public const string NOTE_KEY = '_acme_ca_packing_note';

	// endregion

	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	#[\Override]
	public function register_hooks(): void {
		new ProductDataTab( Plugin::PREFIX . '_packing', $this )->register_hooks();
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
			'label'  => __( 'Packing', 'acme-consumer-a' ),
			'groups' => array(
				array(
					'fields' => array(
						array(
							'type'  => 'text',
							'id'    => self::NOTE_KEY,
							'label' => __( 'Packing note', 'acme-consumer-a' ),
						),
					),
				),
			),
		);
	}

	// endregion
}
