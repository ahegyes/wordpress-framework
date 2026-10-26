<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\WooCommerce;

use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;

trait RequirementFixturesTrait {
	use IsolatesHooksTrait;

	protected string $directory = '';

	#[Before]
	protected function clear_admin_notices(): void {
		remove_all_actions( 'all_admin_notices' );
	}

	#[After]
	protected function remove_plugin(): void {
		wp_set_current_user( 0 );
		if ( '' !== $this->directory ) {
			\array_map( 'unlink', \glob( "$this->directory/*" ) );
			\rmdir( $this->directory );
		}
	}

	// WordPress core reads a plugin's headers and dependencies from the plugins directory, so the fixture lives there.
	protected function write_plugin( array $headers ): string {
		$slug            = 'acme-shop-' . \bin2hex( \random_bytes( 3 ) );
		$this->directory = WP_PLUGIN_DIR . "/$slug";
		\mkdir( $this->directory );

		$lines = array( '<?php', '/**' );
		foreach ( $headers + array( 'Plugin Name' => 'Acme Shop' ) as $name => $value ) {
			$lines[] = " * $name: $value";
		}
		$lines[] = ' */';
		\file_put_contents( "$this->directory/$slug.php", \implode( "\n", $lines ) . "\n" );

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

		return "$this->directory/$slug.php";
	}

	protected function render_notices_as( int $user_id ): string {
		wp_set_current_user( $user_id );
		\ob_start();
		do_action( 'all_admin_notices' );

		return (string) \ob_get_clean();
	}
}
