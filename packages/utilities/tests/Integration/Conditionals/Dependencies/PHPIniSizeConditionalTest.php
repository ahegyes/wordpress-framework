<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Integration\Conditionals\Dependencies;

use DeepWebSolutions\Framework\Utilities\Conditionals\Dependencies\PHPIniSizeConditional;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( PHPIniSizeConditional::class )]
final class PHPIniSizeConditionalTest extends TestCase {
	public function test_passes_when_current_meets_minimum(): void {
		// memory_limit is always at least a few MB (or -1 = unlimited); 1K is a floor everything clears.
		self::assertTrue( ( new PHPIniSizeConditional( 'memory_limit', '1K' ) )->is_met() );
	}

	public function test_byte_shorthand_is_normalized_for_comparison(): void {
		$original = \ini_get( 'memory_limit' );
		self::assertNotFalse( \ini_set( 'memory_limit', '512M' ) );

		try {
			self::assertTrue( ( new PHPIniSizeConditional( 'memory_limit', '256M' ) )->is_met() );
			self::assertTrue( ( new PHPIniSizeConditional( 'memory_limit', '512M' ) )->is_met() );
			self::assertFalse( ( new PHPIniSizeConditional( 'memory_limit', '1G' ) )->is_met() );
		} finally {
			\ini_set( 'memory_limit', (string) $original );
		}
	}

	public function test_unlimited_current_satisfies_any_minimum(): void {
		$original = \ini_get( 'memory_limit' );
		self::assertNotFalse( \ini_set( 'memory_limit', '-1' ) );

		try {
			self::assertTrue( ( new PHPIniSizeConditional( 'memory_limit', '999G' ) )->is_met() );
		} finally {
			\ini_set( 'memory_limit', (string) $original );
		}
	}

	public function test_unknown_directive_fails(): void {
		self::assertFalse( ( new PHPIniSizeConditional( 'dws_not_a_real_directive_xyz', '1K' ) )->is_met() );
	}
}
