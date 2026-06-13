<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Unit\Conditionals\Dependencies;

use DeepWebSolutions\Framework\Utilities\Conditionals\Dependencies\PHPExtensionLoadedConditional;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( PHPExtensionLoadedConditional::class )]
final class PHPExtensionLoadedConditionalTest extends TestCase {
	public function test_known_loaded_extension_returns_true(): void {
		self::assertTrue( ( new PHPExtensionLoadedConditional( 'json' ) )->is_met() );
	}

	public function test_unknown_extension_returns_false(): void {
		self::assertFalse( ( new PHPExtensionLoadedConditional( 'definitely-not-a-real-extension-xyz' ) )->is_met() );
	}
}
