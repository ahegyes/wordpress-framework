<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\WooCommerce;

use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\RendersAdminScreensTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\WritesPluginFilesTrait;
use PHPUnit\Framework\Attributes\Before;

trait RequirementFixturesTrait {
	use IsolatesHooksTrait;
	use RendersAdminScreensTrait;
	use WritesPluginFilesTrait;

	#[Before]
	protected function clear_admin_notices(): void {
		remove_all_actions( 'all_admin_notices' );
	}

	// WordPress core reads a plugin's headers and dependencies from the plugins directory, so the fixture lives there.
	protected function write_plugin( array $headers ): string {
		$plugin_file = $this->write_plugin_file( WP_PLUGIN_DIR, 'acme-shop-' . \bin2hex( \random_bytes( 3 ) ), $headers + array( 'Plugin Name' => 'Acme Shop' ) );

		// WordPress reads every plugin's headers and dependencies once per request.
		wp_clean_plugins_cache( false );
		\Closure::bind(
			static function (): void {
				self::$initialized = false;
				self::$plugins     = null;
			},
			null,
			\WP_Plugin_Dependencies::class
		)();

		return $plugin_file;
	}
}
