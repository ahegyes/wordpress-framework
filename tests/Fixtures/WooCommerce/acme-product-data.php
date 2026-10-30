<?php declare( strict_types=1 );
/**
 * Adds the request spec's product data tab on plugins_loaded, as a plugin without the kernel does.
 *
 * A field's validate may name a behavior as acme:append, acme:reject, acme:reject-silently or acme:record, and its render may be acme:render; each records what it receives in the request's outcome. A group with per_product set records the product the definition receives. The spec may also restrict the tab to simple products, contribute a group through the tab's groups filter, filter a meta key's view reads and record each product save, noting whether it happens while WooCommerce processes the edit form's product.
 *
 * @package Acme
 */

use DeepWebSolutions\Framework\Tests\Integration\Support\RecordedDefinition;
use DeepWebSolutions\Framework\WooCommerce\ProductData\ProductDataTab;

require_once dirname( __DIR__, 3 ) . '/vendor/autoload.php';

$acme_with_behaviors = static function ( array $fields ): array {
	foreach ( $fields as &$field ) {
		$field_id = $field['id'] ?? '';
		if ( 'acme:render' === ( $field['render'] ?? null ) ) {
			$field['render'] = static function ( array $field, mixed $value, string $name, string $id ) use ( $field_id ): string {
				$GLOBALS['acme_outcome']['records'][] = array( 'render', $field_id, $value, $name, $id );

				return '<input type="hidden" name="' . esc_attr( $name ) . '" value="" /><input type="text" class="acme-widget" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( is_string( $value ) ? $value : '' ) . '" />';
			};
		}

		$behavior = $field['validate'] ?? null;
		if ( is_string( $behavior ) && str_starts_with( $behavior, 'acme:' ) ) {
			$field['validate'] = static function ( mixed $raw ) use ( $behavior, $field_id ): mixed {
				$GLOBALS['acme_outcome']['records'][] = array( 'validate', $field_id, $raw );

				return match ( $behavior ) {
					'acme:append'          => $raw . '!',
					'acme:reject'          => new WP_Error( 'acme_rejected', 'Use at most four characters.' ),
					'acme:reject-silently' => new WP_Error( 'acme_rejected' ),
					'acme:record'          => $raw,
				};
			};
		}
	}

	return $fields;
};

add_action(
	'plugins_loaded',
	static function () use ( $spec, $acme_with_behaviors ): void {
		$definition = new RecordedDefinition(
			static function ( string $tab_id, WC_Product $product ) use ( $spec, $acme_with_behaviors ): ?array {
				if ( 'acme:simple' === ( $spec['supports'] ?? null ) && ! $product->is_type( 'simple' ) ) {
					return null;
				}

				$groups = array();
				foreach ( $spec['groups'] as $group ) {
					if ( $group['per_product'] ?? false ) {
						$GLOBALS['acme_outcome']['records'][] = array( 'fields', $product->get_id() );
					}
					$groups[] = array( 'fields' => $acme_with_behaviors( $group['fields'] ) ) + array_intersect_key( $group, array( 'class' => true ) );
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

		( new ProductDataTab( $spec['id'], $definition ) )->register_hooks();
	}
);

if ( isset( $spec['contributed'] ) ) {
	add_filter(
		"{$spec['id']}/product_data_tab/groups",
		static function ( array $groups, WC_Product $product ) use ( $spec, $acme_with_behaviors ): array {
			$GLOBALS['acme_outcome']['records'][] = array( 'groups', $product->get_id() );
			$groups[]                             = array( 'fields' => $acme_with_behaviors( $spec['contributed'] ) );

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
