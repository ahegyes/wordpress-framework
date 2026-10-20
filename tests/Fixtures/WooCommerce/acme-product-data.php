<?php declare( strict_types=1 );
/**
 * Adds the request spec's product data tab on plugins_loaded, as a plugin without the kernel does.
 *
 * A field's validate may name a behavior as acme:append, acme:reject, acme:reject-silently or acme:record, and its render may be acme:render; each records what it receives in the request's outcome. A group with per_product set lists its fields through a closure that records the product it receives. The spec may also restrict the tab to simple products, contribute a group through the tab's groups filter, filter a meta key's view reads and record each product save.
 *
 * @package Acme
 */

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
		$groups = array();
		foreach ( $spec['groups'] as $group ) {
			$fields = $acme_with_behaviors( $group['fields'] );
			if ( $group['per_product'] ?? false ) {
				$fields = static function ( WC_Product $product ) use ( $fields ): array {
					$GLOBALS['acme_outcome']['records'][] = array( 'fields', $product->get_id() );

					return $fields;
				};
			}
			$groups[] = array( 'fields' => $fields ) + array_intersect_key( $group, array( 'class' => true ) );
		}

		$tab = array(
			'label'  => $spec['label'],
			'groups' => $groups,
		) + array_intersect_key(
			$spec,
			array(
				'priority' => true,
				'class'    => true,
			)
		);
		if ( 'acme:simple' === ( $spec['supports'] ?? null ) ) {
			$tab['supports'] = static fn ( WC_Product $product ): bool => $product->is_type( 'simple' );
		}

		( new ProductDataTab( $spec['id'], static fn (): array => $tab ) )->register_hooks();
	}
);

if ( isset( $spec['contributed'] ) ) {
	add_filter(
		"{$spec['id']}_groups",
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
			$GLOBALS['acme_outcome']['records'][] = array( 'saved', $product->get_id() );
		}
	);
}
