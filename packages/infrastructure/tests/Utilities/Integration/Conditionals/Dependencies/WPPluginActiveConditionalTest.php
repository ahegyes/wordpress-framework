<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Integration\Conditionals\Dependencies;

use DeepWebSolutions\Framework\Utilities\Conditionals\Dependencies\WPPluginActiveConditional;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( WPPluginActiveConditional::class )]
final class WPPluginActiveConditionalTest extends TestCase {
	public function test_unknown_plugin_returns_false(): void {
		$conditional = new WPPluginActiveConditional( 'definitely-not-installed/definitely-not-installed.php' );

		self::assertFalse( $conditional->is_met() );
	}

	public function test_active_plugin_returns_true(): void {
		$basename = 'dws-active-fixture/dws-active-fixture.php';
		$inject   = static function ( $plugins ) use ( $basename ) {
			$plugins   = (array) $plugins;
			$plugins[] = $basename;
			return $plugins;
		};
		\add_filter( 'option_active_plugins', $inject );

		try {
			self::assertTrue( ( new WPPluginActiveConditional( $basename ) )->is_met() );
		} finally {
			\remove_filter( 'option_active_plugins', $inject );
		}
	}
}
