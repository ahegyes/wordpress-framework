<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\WooCommerce;

use PHPUnit\Framework\Attributes\After;

trait ProductFixturesTrait {
	protected array $product_ids = array();

	protected function make_product( array $meta = array(), string $type = 'simple' ): \WC_Product {
		$product = \wc_get_product_object( $type );
		$product->set_name( 'Acme product' );
		foreach ( $meta as $key => $value ) {
			$product->update_meta_data( $key, $value );
		}
		$product->save();
		$this->product_ids[] = $product->get_id();

		return $product;
	}

	#[After]
	protected function delete_products(): void {
		foreach ( $this->product_ids as $product_id ) {
			\wp_delete_post( $product_id, true );
		}
		\delete_option( 'woocommerce_meta_box_errors' );
		\wp_cache_flush();
	}
}
