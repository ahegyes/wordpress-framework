<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Support;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;

trait IsolatesStorageTrait {
	protected string $prefix;

	#[Before]
	protected function use_a_fresh_prefix(): void {
		$this->prefix = 'acme_' . \bin2hex( \random_bytes( 3 ) );
	}

	#[After]
	protected function delete_stored_rows(): void {
		global $wpdb;

		foreach ( \is_multisite() ? \get_sites( array( 'fields' => 'ids' ) ) : array( 1 ) as $site_id ) {
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s', $wpdb->get_blog_prefix( $site_id ) . 'options', $wpdb->esc_like( $this->prefix ) . '%', $wpdb->esc_like( "_transient_$this->prefix" ) . '%', $wpdb->esc_like( "_transient_timeout_$this->prefix" ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Removes the rows the test created.
		}
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE meta_key LIKE %s', $wpdb->usermeta, '%' . $wpdb->esc_like( $this->prefix ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Removes the rows the test created.
		if ( \is_multisite() ) {
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE meta_key LIKE %s', $wpdb->sitemeta, $wpdb->esc_like( $this->prefix ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Removes the rows the test created.
		}
		\wp_cache_flush();
	}
}
