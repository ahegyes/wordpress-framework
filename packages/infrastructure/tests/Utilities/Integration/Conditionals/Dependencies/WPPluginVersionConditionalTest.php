<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Integration\Conditionals\Dependencies;

use DeepWebSolutions\Framework\Shared\Version\Version;
use DeepWebSolutions\Framework\Utilities\Conditionals\Dependencies\WPPluginVersionConditional;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * The plugin-header-fixture plugin (Version 2.5.1) is mounted by wp-env; activating it via the
 * 'option_active_plugins' filter lets the conditional read a real Version header from disk.
 */
#[CoversClass( WPPluginVersionConditional::class )]
#[UsesClass( Version::class )]
final class WPPluginVersionConditionalTest extends TestCase {
	private const BASENAME = 'plugin-header-fixture/dws-fixture.php';

	/**
	 * @var callable|null
	 */
	private $inject = null;

	protected function tearDown(): void {
		if ( null !== $this->inject ) {
			\remove_filter( 'option_active_plugins', $this->inject );
			$this->inject = null;
		}
		parent::tearDown();
	}

	public function test_inactive_plugin_is_unmet_regardless_of_version(): void {
		$conditional = new WPPluginVersionConditional( self::BASENAME, Version::from_string( '1.0.0' ) );

		self::assertFalse( $conditional->is_met() );
	}

	public function test_unknown_plugin_is_unmet(): void {
		$this->activate_fixture( 'definitely-not-installed/definitely-not-installed.php' );
		$conditional = new WPPluginVersionConditional( 'definitely-not-installed/definitely-not-installed.php', Version::from_string( '1.0.0' ) );

		self::assertFalse( $conditional->is_met() );
	}

	public function test_active_plugin_at_or_above_the_minimum_is_met(): void {
		$this->activate_fixture( self::BASENAME );

		self::assertTrue( ( new WPPluginVersionConditional( self::BASENAME, Version::from_string( '2.0.0' ) ) )->is_met() );
		self::assertTrue( ( new WPPluginVersionConditional( self::BASENAME, Version::from_string( '2.5.1' ) ) )->is_met() );
	}

	public function test_active_plugin_below_the_minimum_is_unmet(): void {
		$this->activate_fixture( self::BASENAME );

		self::assertFalse( ( new WPPluginVersionConditional( self::BASENAME, Version::from_string( '2.5.2' ) ) )->is_met() );
		self::assertFalse( ( new WPPluginVersionConditional( self::BASENAME, Version::from_string( '999.0.0' ) ) )->is_met() );
	}

	private function activate_fixture( string $basename ): void {
		$this->inject = static function ( $plugins ) use ( $basename ) {
			$plugins   = (array) $plugins;
			$plugins[] = $basename;
			return $plugins;
		};
		\add_filter( 'option_active_plugins', $this->inject );
	}
}
