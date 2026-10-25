<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\WooCommerce\Uninstall;

use DeepWebSolutions\Framework\Tests\Integration\Core\Uninstall\UninstallFixturesTrait;
use PHPUnit\Framework\Attributes\After;

trait OrderMetaFixturesTrait {
	use UninstallFixturesTrait;

	protected int $order_id = 0;

	protected function order_footprint(): array {
		$p = $this->prefix;

		return array(
			'meta'          => array(
				'order'      => array( "_{$p}_flag" ),
				'order_item' => array( "_{$p}_item" ),
			),
			'meta_prefixes' => array( 'order' => array( "_{$p}_cache_" ) ),
		);
	}

	// Each table gets the footprint's keys, their letter-case twins and keys that only resemble them.
	protected function seed_order_meta(): void {
		global $wpdb;

		$p              = $this->prefix;
		$this->order_id = \wp_insert_post(
			array(
				'post_title'  => "$p order",
				'post_status' => 'draft',
			)
		);
		foreach ( $this->order_meta_keys() as $key ) {
			\add_post_meta( $this->order_id, $key, 'x' );
			if ( $this->table_exists( 'wc_orders_meta' ) ) {
				$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Seeds the order meta table WooCommerce would write.
					"{$wpdb->prefix}wc_orders_meta",
					array(
						'order_id'   => $this->order_id,
						'meta_key'   => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- A seeded row.
						'meta_value' => 'x', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- A seeded row.
					)
				);
			}
		}
		if ( $this->table_exists( 'woocommerce_order_itemmeta' ) ) {
			foreach ( array( "_{$p}_item", \strtoupper( "_{$p}_item" ), "_{$p}_kept" ) as $key ) {
				$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Seeds the order item meta table WooCommerce would write.
					"{$wpdb->prefix}woocommerce_order_itemmeta",
					array(
						'order_item_id' => $this->order_id,
						'meta_key'      => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- A seeded row.
						'meta_value'    => 'x', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- A seeded row.
					)
				);
			}
		}
	}

	protected function order_meta_keys(): array {
		$p = $this->prefix;

		return array( "_{$p}_flag", \strtoupper( "_{$p}_flag" ), "_{$p}_cache_a", "_{$p}_cache_b", "_{$p}_cacheZ", \strtoupper( "_{$p}_cache_c" ), "_{$p}_kept" );
	}

	protected function kept_order_meta_keys(): array {
		$p = $this->prefix;

		return array( \strtoupper( "_{$p}_cache_c" ), \strtoupper( "_{$p}_flag" ), "_{$p}_cacheZ", "_{$p}_kept" );
	}

	protected function kept_order_item_meta_keys(): array {
		$p = $this->prefix;

		return array( \strtoupper( "_{$p}_item" ), "_{$p}_kept" );
	}

	// The table's collation matches the twins too, so the keys are read as bytes.
	protected function meta_keys_in( string $table ): array {
		global $wpdb;

		$keys = $wpdb->get_col( $wpdb->prepare( 'SELECT CAST( meta_key AS BINARY ) FROM %i WHERE meta_key LIKE %s', $wpdb->prefix . $table, '%' . $wpdb->esc_like( $this->prefix ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reads the rows the test seeded.
		\sort( $keys );

		return $keys;
	}

	#[After]
	protected function delete_order_meta_rows(): void {
		global $wpdb;

		foreach ( array( 'postmeta', 'wc_orders_meta', 'woocommerce_order_itemmeta' ) as $table ) {
			if ( $this->table_exists( $table ) ) {
				$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE meta_key LIKE %s', $wpdb->prefix . $table, '%' . $wpdb->esc_like( $this->prefix ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Removes the rows the test seeded.
			}
		}
		if ( 0 !== $this->order_id ) {
			\wp_delete_post( $this->order_id, true );
		}
	}
}
