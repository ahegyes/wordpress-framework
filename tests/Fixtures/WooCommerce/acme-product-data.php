<?php declare( strict_types=1 );
/**
 * Adds the request spec's product data tab on plugins_loaded.
 *
 * A field's validate and render may name a behavior RecordedDefinition::with_behaviors() lists, which records what it receives in the request's outcome. A group with per_product set records the product the definition receives. The spec may also restrict the tab to simple products, contribute a group through the tab's groups filter, filter a meta key's view reads and record each product save, noting whether it happens while WooCommerce processes the edit form's product.
 *
 * @package Acme
 */

use DeepWebSolutions\Framework\Tests\Integration\Support\RecordedDefinition;
use DeepWebSolutions\Framework\WooCommerce\ProductData\ProductDataTab;

require_once dirname( __DIR__, 3 ) . '/vendor/autoload.php';

add_action(
	'plugins_loaded',
	static function () use ( $spec ): void {
		$definition = new RecordedDefinition(
			static function ( string $tab_id, WC_Product $product ) use ( $spec ): ?array {
				if ( 'acme:simple' === ( $spec['supports'] ?? null ) && ! $product->is_type( 'simple' ) ) {
					return null;
				}

				$groups = array();
				foreach ( $spec['groups'] as $group ) {
					if ( $group['per_product'] ?? false ) {
						$GLOBALS['acme_outcome']['records'][] = array( 'fields', $product->get_id() );
					}

					$groups[] = array( 'fields' => RecordedDefinition::with_behaviors( $group['fields'] ) ) + array_intersect_key( $group, array( 'class' => true ) );
				}

				return array(
					'label'  => $spec['label'],
					'groups' => $groups,
				) + array_intersect_key(
					$spec,
					array(
						'priority' => true,
						'class'    => true,
					)
				);
			}
		);

		new ProductDataTab( $spec['id'], $definition )->register_hooks();
	}
);

if ( isset( $spec['contributed'] ) ) {
	add_filter(
		"{$spec['id']}/product_data_tab/groups",
		static function ( array $groups, WC_Product $product ) use ( $spec ): array {
			$GLOBALS['acme_outcome']['records'][] = array( 'groups', $product->get_id() );
			$groups[]                             = array( 'fields' => RecordedDefinition::with_behaviors( $spec['contributed'] ) );

			return $groups;
		},
		10,
		2
	);
}

if ( isset( $spec['filtered'] ) ) {
	add_filter( "woocommerce_product_get_{$spec['filtered']}", static fn (): string => 'filtered' );
}

if ( $spec['count_saves'] ?? false ) {
	add_action(
		'woocommerce_after_product_object_save',
		static function ( WC_Product $product ): void {
			$GLOBALS['acme_outcome']['records'][] = array( 'saved', $product->get_id(), doing_action( 'woocommerce_admin_process_product_object' ) );
		}
	);
}
