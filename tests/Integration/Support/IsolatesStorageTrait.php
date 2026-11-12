<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Support;

use DeepWebSolutions\Framework\Core\PluginIdentity;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;

trait IsolatesStorageTrait {
	protected string $prefix;

	protected string $slug;

	protected string $hook_root;

	protected PluginIdentity $identity;

	// The slug differs from the prefix, so a row tells a hook or REST name from a storage key.
	#[Before]
	protected function use_a_fresh_prefix(): void {
		$suffix          = \bin2hex( \random_bytes( 3 ) );
		$this->prefix    = "acme_$suffix";
		$this->slug      = "acme-plugin-$suffix";
		$this->hook_root = "acme_plugin_$suffix";
		$this->identity  = new PluginIdentity( \dirname( __DIR__, 2 ) . '/Fixtures/Core/acme-boot.php', $this->slug, $this->prefix );
	}

	#[After]
	protected function delete_stored_rows(): void {
		self::delete_prefixed_options( $this->prefix );
		wp_cache_flush();
	}

	// Deletes every site's options and transients, the user options and the network options whose names hold the prefix.
	protected static function delete_prefixed_options( string $prefix ): void {
		global $wpdb;

		foreach ( is_multisite() ? get_sites( array( 'fields' => 'ids' ) ) : array( 1 ) as $site_id ) {
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s', $wpdb->get_blog_prefix( $site_id ) . 'options', $wpdb->esc_like( $prefix ) . '%', $wpdb->esc_like( "_transient_$prefix" ) . '%', $wpdb->esc_like( "_transient_timeout_$prefix" ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Removes the rows the test created.
		}

		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE meta_key LIKE %s', $wpdb->usermeta, '%' . $wpdb->esc_like( $prefix ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Removes the rows the test created.
		if ( is_multisite() ) {
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE meta_key LIKE %s', $wpdb->sitemeta, $wpdb->esc_like( $prefix ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Removes the rows the test created.
		}
	}
}
