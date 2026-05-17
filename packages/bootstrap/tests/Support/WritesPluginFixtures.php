<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Bootstrap\Tests\Support;

use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\After;

trait WritesPluginFixtures {
	/** @var list<string> */
	private array $fixture_paths = array();

	#[Before]
	protected function reset_admin_notices(): void {
		\remove_all_actions( 'admin_notices' );
	}

	#[After]
	protected function cleanup_written_fixtures(): void {
		foreach ( $this->fixture_paths as $path ) {
			if ( \is_file( $path ) ) {
				\unlink( $path );
			}

			$dir = \dirname( $path );
			if ( \is_dir( $dir ) && array() === \glob( $dir . '/*' ) ) {
				\rmdir( $dir );
			}
		}

		$this->fixture_paths = array();
	}

	protected function render_admin_notices(): string {
		\ob_start();
		\do_action( 'admin_notices' );
		return (string) \ob_get_clean();
	}

	/**
	 * @param array<string, string> $headers
	 */
	protected function write_fixture( string $basename, array $headers ): void {
		$path = WP_PLUGIN_DIR . '/' . $basename;
		$dir  = \dirname( $path );
		if ( ! \is_dir( $dir ) ) {
			\mkdir( $dir, 0755, true );
		}

		$lines = array( '<?php', '/**' );
		foreach ( $headers as $key => $value ) {
			$lines[] = ' * ' . $key . ': ' . $value;
		}
		$lines[] = ' */';

		\file_put_contents( $path, \implode( "\n", $lines ) . "\n" );
		$this->fixture_paths[] = $path;
	}
}
