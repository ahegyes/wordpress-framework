<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Support;

use DeepWebSolutions\Framework\Tests\Support\CopiesFixturesTrait;
use PHPUnit\Framework\Attributes\After;

trait WritesPluginFilesTrait {
	use CopiesFixturesTrait;

	protected array $plugin_files = array();

	// Writes the entry file of a plugin folder named after its slug, with a header comment that leaves out every header given as '', and returns its path; the cleanup removes the folder.
	protected function write_plugin_file( string $root, string $slug, array $headers ): string {
		$lines = array();
		foreach ( $headers as $name => $value ) {
			if ( '' !== $value ) {
				$lines[] = " * $name: $value";
			}
		}

		$plugin_file = "$root/$slug/$slug.php";
		wp_mkdir_p( "$root/$slug" );
		\file_put_contents( $plugin_file, "<?php\n/**\n" . \implode( "\n", $lines ) . "\n */\n" );
		if ( ! \in_array( $plugin_file, $this->plugin_files, true ) ) {
			$this->plugin_files[] = $plugin_file;
		}

		return $plugin_file;
	}

	#[After]
	protected function remove_plugin_files(): void {
		foreach ( $this->plugin_files as $plugin_file ) {
			self::remove_tree( \dirname( $plugin_file ) );
		}

		$this->plugin_files = array();
	}
}
