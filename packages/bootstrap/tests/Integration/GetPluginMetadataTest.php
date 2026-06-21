<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Bootstrap\Tests\Integration;

use DeepWebSolutions\Framework\Bootstrap\Tests\Support\WritesPluginFixtures;
use PHPUnit\Framework\TestCase;

use function DeepWebSolutions\Framework\Bootstrap\Plugin\get_plugin_metadata;

final class GetPluginMetadataTest extends TestCase {
	use WritesPluginFixtures;

	private string $plugin_basename = 'dws-framework-test-plugin/dws-framework-test-plugin.php';

	public function test_reads_real_headers(): void {
		$metadata = get_plugin_metadata( $this->plugin_basename );

		self::assertSame( 'DWS Framework Test Plugin', $metadata['Name'] );
		self::assertSame( '1.2.3', $metadata['Version'] );
		self::assertSame( '8.5', $metadata['RequiresPHP'] );
		self::assertSame( '7.0', $metadata['RequiresWP'] );
	}

	public function test_returns_cached_value_after_file_mutation(): void {
		$basename = 'dws-cache-test/dws-cache-test.php';
		$this->write_fixture(
			$basename,
			array(
				'Plugin Name' => 'Original Name',
				'Version'     => '1.0.0',
			)
		);

		$first = get_plugin_metadata( $basename );

		$this->write_fixture(
			$basename,
			array(
				'Plugin Name' => 'Mutated Name',
				'Version'     => '2.0.0',
			)
		);

		$second = get_plugin_metadata( $basename );

		self::assertSame( 'Original Name', $second['Name'] );
		self::assertSame( '1.0.0', $second['Version'] );
		self::assertSame( $first, $second );
	}

	public function test_returns_empty_string_headers_when_plugin_file_missing(): void {
		$basename = 'dws-does-not-exist/dws-does-not-exist.php';

		$metadata = get_plugin_metadata( $basename );

		self::assertIsArray( $metadata );
		self::assertSame( '', $metadata['Name'] );
		self::assertSame( '', $metadata['Version'] );
		self::assertSame( '', $metadata['RequiresPHP'] );
		self::assertSame( '', $metadata['RequiresWP'] );
		self::assertFalse( $metadata['Network'] );
		// Missing file: the metadata reader derives TextDomain from the plugin slug.
		self::assertSame( 'dws-does-not-exist', $metadata['TextDomain'] );
	}

	public function test_translate_true_uses_separate_cache_key(): void {
		$basename = 'dws-translate-test/dws-translate-test.php';
		$this->write_fixture(
			$basename,
			array(
				'Plugin Name' => 'Translate Original',
				'Version'     => '1.0.0',
			)
		);

		$raw_first = get_plugin_metadata( $basename, false );

		$this->write_fixture(
			$basename,
			array(
				'Plugin Name' => 'Translate Mutated',
				'Version'     => '2.0.0',
			)
		);

		$translated_first = get_plugin_metadata( $basename, true );

		self::assertSame( 'Translate Original', $raw_first['Name'] );
		self::assertSame( 'Translate Mutated', $translated_first['Name'] );
	}

	public function test_missing_directoryless_basename_leaves_text_domain_empty(): void {
		$metadata = get_plugin_metadata( 'dws-loose.php' );

		self::assertSame( '', $metadata['Name'] );
		self::assertSame( '', $metadata['TextDomain'] );
	}

	public function test_missing_nested_basename_leaves_text_domain_empty(): void {
		$metadata = get_plugin_metadata( 'vendor/dws-nested/plugin.php' );

		self::assertSame( '', $metadata['Name'] );
		self::assertSame( '', $metadata['TextDomain'] );
	}
}
