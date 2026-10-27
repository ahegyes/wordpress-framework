<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Php74\Support;

use DeepWebSolutions\Framework\Tests\Support\CopiesFixturesTrait;
use PHPUnit\Framework\Attributes\After;

trait RunsPhp74SiteTrait {
	use CopiesFixturesTrait;

	protected const string ROOT = __DIR__ . '/../../..';

	// .wp-env.php74.json points WP_PLUGIN_DIR here, inside the repository the environment maps.
	protected const string PLUGINS = self::ROOT . '/tests/.cache/php74-plugins';

	// Copies a built fixture plugin into the PHP 7.4 site's plugins directory and returns its plugin file.
	protected function install_on_php74( string $fixture, string $dir, array $headers = array() ): string {
		return "$dir/" . self::copy_fixture( $fixture, self::PLUGINS . "/$dir", array(), $headers );
	}

	// Activates exactly the given plugins on the PHP 7.4 site, writing the option directly because WordPress refuses to activate a plugin below its floors, then records what an administrator sees there.
	protected function php74_request( array $plugins ): array {
		$this->wp_cli( 'option', 'update', 'active_plugins', \json_encode( $plugins, \JSON_THROW_ON_ERROR ), '--format=json', '--skip-plugins' );
		// The file is required rather than run through eval-file, which evaluates it after its opening tag, where a strict_types declaration fails.
		$lines = \preg_split( '/\R/', \trim( $this->wp_cli( 'eval', "require ABSPATH . 'wp-content/mu-plugins/wp-framework/tests/Php74/Support/php74-request.php';" ) ) );

		return \json_decode( (string) \end( $lines ), true, flags: \JSON_THROW_ON_ERROR );
	}

	protected function wp_cli( string ...$args ): string {
		$descriptors = array(
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		);
		$process     = \proc_open( array( self::ROOT . '/node_modules/.bin/wp-env', '--config', '.wp-env.php74.json', 'run', 'cli', 'wp', ...$args ), $descriptors, $pipes, self::ROOT, array( 'CI' => '1' ) + \getenv() ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open -- Runs WP-CLI in the PHP 7.4 environment.
		self::assertIsResource( $process );
		$output = (string) \stream_get_contents( $pipes[1] );
		$errors = (string) \stream_get_contents( $pipes[2] );
		\fclose( $pipes[1] );
		\fclose( $pipes[2] );
		self::assertSame( 0, \proc_close( $process ), "Start the PHP 7.4 environment with `composer test:integration:php74`.\n$errors$output" );

		return $output;
	}

	#[After]
	protected function remove_php74_plugins(): void {
		$this->wp_cli( 'option', 'update', 'active_plugins', '[]', '--format=json', '--skip-plugins' );
		self::remove_tree( self::PLUGINS );
	}
}
