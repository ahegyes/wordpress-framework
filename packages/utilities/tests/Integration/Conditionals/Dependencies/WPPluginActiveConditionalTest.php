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
}
