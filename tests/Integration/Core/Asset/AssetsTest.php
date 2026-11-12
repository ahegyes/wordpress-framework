<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Core\Asset;

use DeepWebSolutions\Framework\Core\Asset\Assets;
use DeepWebSolutions\Framework\Core\PluginIdentity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( Assets::class )]
final class AssetsTest extends TestCase {
	protected const string PLUGIN_FILE = __DIR__ . '/../../../Fixtures/Core/acme-boot.php';

	protected const string SCRIPT = 'acme-assets-script';

	protected const string STYLE = 'acme-assets-style';

	#[\Override]
	protected function tearDown(): void {
		wp_deregister_script( self::SCRIPT );
		wp_deregister_style( self::STYLE );
	}

	public function test_a_script_registers_with_its_build_s_script_dependencies_version_and_translations(): void {
		self::assertTrue( new Assets( new PluginIdentity( self::PLUGIN_FILE, 'acme-plugin', 'acme' ) )->register_script( self::SCRIPT, 'assets/build/editor.js', array( 'in_footer' => true ) ) );

		$script = wp_scripts()->registered[ self::SCRIPT ];
		self::assertSame( plugin_dir_url( self::PLUGIN_FILE ) . 'assets/build/editor.js', $script->src );
		self::assertSame( array( 'wp-blocks', 'wp-i18n' ), $script->deps );
		self::assertSame( 'a1b2c3', $script->ver );
		self::assertSame( 1, $script->extra['group'] ?? null );
		self::assertSame( 'acme-plugin', $script->textdomain );
		self::assertSame( plugin_dir_path( self::PLUGIN_FILE ) . 'languages', $script->translations_path );
	}

	public function test_a_style_registers_with_its_build_s_version_and_a_right_to_left_variant(): void {
		self::assertTrue( new Assets( new PluginIdentity( self::PLUGIN_FILE, 'acme-plugin', 'acme' ) )->register_style( self::STYLE, 'assets/build/editor.css' ) );

		$style = wp_styles()->registered[ self::STYLE ];
		self::assertSame( plugin_dir_url( self::PLUGIN_FILE ) . 'assets/build/editor.css', $style->src );
		self::assertSame( array(), $style->deps );
		self::assertSame( 'a1b2c3', $style->ver );
		self::assertSame( 'replace', $style->extra['rtl'] ?? null );
	}

	public function test_the_style_chunk_of_an_entry_takes_the_entry_s_version(): void {
		self::assertTrue( new Assets( new PluginIdentity( self::PLUGIN_FILE, 'acme-plugin', 'acme' ) )->register_style( self::STYLE, 'assets/build/style-editor.css' ) );

		self::assertSame( 'a1b2c3', wp_styles()->registered[ self::STYLE ]->ver );
	}

	public function test_a_stylesheet_without_an_asset_file_is_versioned_by_its_modification_time(): void {
		self::assertTrue( new Assets( new PluginIdentity( self::PLUGIN_FILE, 'acme-plugin', 'acme' ) )->register_style( self::STYLE, 'assets/build/vendor.css' ) );

		self::assertSame( (string) \filemtime( plugin_dir_path( self::PLUGIN_FILE ) . 'assets/build/vendor.css' ), wp_styles()->registered[ self::STYLE ]->ver );
	}

	public function test_a_script_named_like_a_style_chunk_takes_no_entry_s_asset_file(): void {
		self::assertTrue( new Assets( new PluginIdentity( self::PLUGIN_FILE, 'acme-plugin', 'acme' ) )->register_script( self::SCRIPT, 'assets/build/style-editor.js' ) );

		$script = wp_scripts()->registered[ self::SCRIPT ];
		self::assertSame( array( 'wp-i18n' ), $script->deps );
		self::assertSame( (string) \filemtime( plugin_dir_path( self::PLUGIN_FILE ) . 'assets/build/style-editor.js' ), $script->ver );
	}

	public function test_a_script_built_without_an_asset_file_registers_without_dependencies_at_its_modification_time(): void {
		self::assertTrue( new Assets( new PluginIdentity( self::PLUGIN_FILE, 'acme-plugin', 'acme' ) )->register_script( self::SCRIPT, 'assets/build/vendor.js' ) );

		$script = wp_scripts()->registered[ self::SCRIPT ];
		self::assertSame( array( 'wp-i18n' ), $script->deps );
		self::assertSame( (string) \filemtime( plugin_dir_path( self::PLUGIN_FILE ) . 'assets/build/vendor.js' ), $script->ver );
	}

	public function test_a_handle_registered_elsewhere_keeps_its_script_and_gains_no_translations(): void {
		wp_register_script( self::SCRIPT, 'https://example.com/other.js', array(), '1', true );

		self::assertFalse( new Assets( new PluginIdentity( self::PLUGIN_FILE, 'acme-plugin', 'acme' ) )->register_script( self::SCRIPT, 'assets/build/editor.js' ) );
		self::assertSame( 'https://example.com/other.js', wp_scripts()->registered[ self::SCRIPT ]->src );
		self::assertNull( wp_scripts()->registered[ self::SCRIPT ]->textdomain );
	}
}
