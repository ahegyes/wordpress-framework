<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Support;

use DeepWebSolutions\Framework\Tests\Support\CopiesFixturesTrait;
use PHPUnit\Framework\Attributes\After;

trait InstallsConsumersTrait {
	use CopiesFixturesTrait;
	use IsolatesStorageTrait;
	use RunsAdminRequestsTrait;

	protected array $consumer_copies = array();

	protected array $plugin_options = array();

	// Copies a built fixture plugin under WP_PLUGIN_DIR, where WordPress core re-reads a plugin's headers, and returns its plugin file.
	protected function install_consumer( string $fixture, string $dir, array $remove = array(), array $headers = array() ): string {
		$this->consumer_copies[] = WP_PLUGIN_DIR . "/$dir";
		$entry                   = self::copy_fixture( $fixture, WP_PLUGIN_DIR . "/$dir", $remove, $headers );
		wp_clean_plugins_cache();

		return "$dir/$entry";
	}

	// Stores the site's active plugins in the given load order; the cleanup restores them, with the recently activated list an activation rewrites.
	protected function store_active_plugins( array $plugins ): void {
		$this->plugin_options += array(
			'active_plugins'     => get_option( 'active_plugins' ),
			'recently_activated' => get_option( 'recently_activated' ),
		);
		update_option( 'active_plugins', $plugins );
	}

	protected function consumer_request( string $method, string $path, int $user_id, array $spec, array $post = array() ): array {
		return $this->admin_request( $method, $path, $user_id, __DIR__ . '/consumer-request.php', $spec, $post );
	}

	#[After]
	protected function remove_consumers(): void {
		global $wpdb;

		foreach ( $this->consumer_copies as $copy ) {
			self::remove_tree( $copy );
		}
		$this->consumer_copies = array();

		foreach ( $this->plugin_options as $option => $value ) {
			false === $value ? delete_option( $option ) : update_option( $option, $value );
		}
		$this->plugin_options = array();

		foreach ( array( 'acme_ca_', 'acme_cb_', 'acme_pc_', 'acme_ps_', 'acme_pw_' ) as $prefix ) {
			self::delete_prefixed_options( $prefix );
		}
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE meta_key LIKE %s OR meta_key LIKE %s', $wpdb->postmeta, '\_acme\_ca\_%', '\_acme\_pw\_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Removes the rows consumer-a and the woocommerce persona stored.
		wp_clean_plugins_cache();
		wp_cache_flush();
	}
}
