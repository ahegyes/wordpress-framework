<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\WooCommerce\EditScreen;

use PHPUnit\Framework\Attributes\After;

trait OrderFixturesTrait {
	protected array $orders = array();

	public static function order_storages(): array {
		return array(
			'order-tables' => array( 'hpos' ),
			'posts-table'  => array( 'posts' ),
		);
	}

	// The environment stores orders in WooCommerce's order tables; the filter moves this process to the posts table without update_option()'s sync guard.
	protected function use_order_storage( string $storage ): void {
		if ( 'posts' === $storage ) {
			\add_filter( 'pre_option_woocommerce_custom_orders_table_enabled', static fn (): string => 'no' );
		}
	}

	protected function make_order( array $meta = array() ): \WC_Order {
		$order = new \WC_Order();
		foreach ( $meta as $key => $value ) {
			$order->update_meta_data( $key, $value );
		}
		$order->save();
		$this->orders[] = $order;

		return $order;
	}

	protected function order_meta( int $order_id, string $key ): array {
		\wp_cache_flush();
		$order = \wc_get_order( $order_id );
		self::assertInstanceOf( \WC_Order::class, $order );

		return \array_values( \array_map( static fn ( \WC_Meta_Data $meta ): mixed => $meta->value, $order->get_meta( $key, false, 'edit' ) ) );
	}

	// Each order keeps the data store it was created with, so it deletes from its own storage.
	#[After]
	protected function delete_orders(): void {
		foreach ( $this->orders as $order ) {
			$order->delete( true );
		}
		\wp_cache_flush();
	}
}
