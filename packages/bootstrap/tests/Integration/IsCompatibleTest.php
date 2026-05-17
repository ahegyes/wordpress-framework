<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Bootstrap\Tests\Integration;

use PHPUnit\Framework\TestCase;

use function DeepWebSolutions\Framework\Bootstrap\Environment\is_php_compatible;
use function DeepWebSolutions\Framework\Bootstrap\Environment\is_wp_compatible;

final class IsCompatibleTest extends TestCase {
	public function test_is_php_compatible_returns_true_below_runtime(): void {
		self::assertTrue( is_php_compatible( '5.0' ) );
	}

	public function test_is_php_compatible_returns_false_above_runtime(): void {
		self::assertFalse( is_php_compatible( '99.99' ) );
	}

	public function test_is_wp_compatible_returns_true_below_runtime(): void {
		self::assertTrue( is_wp_compatible( '1.0' ) );
	}

	public function test_is_wp_compatible_returns_false_above_runtime(): void {
		self::assertFalse( is_wp_compatible( '99.99' ) );
	}
}
