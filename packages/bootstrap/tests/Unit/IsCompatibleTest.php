<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Bootstrap\Tests\Unit;

use PHPUnit\Framework\TestCase;

use function DeepWebSolutions\Framework\Bootstrap\is_php_compatible;
use function DeepWebSolutions\Framework\Bootstrap\is_wp_compatible;

/**
 * Unit tests for the WP-native version-check wrappers — exercises the
 * "outside WP" fallback path where `is_php_version_compatible` and
 * `is_wp_version_compatible` are absent (WP not loaded). Both wrappers
 * must return false in that case.
 */
final class IsCompatibleTest extends TestCase {
	public function test_is_php_compatible_returns_false_when_wp_native_missing(): void {
		self::assertFalse( \function_exists( '\is_php_version_compatible' ), 'WP must not be loaded for unit tests.' );
		self::assertFalse( is_php_compatible( '5.0' ) );
		self::assertFalse( is_php_compatible( '99.99' ) );
	}

	public function test_is_wp_compatible_returns_false_when_wp_native_missing(): void {
		self::assertFalse( \function_exists( '\is_wp_version_compatible' ), 'WP must not be loaded for unit tests.' );
		self::assertFalse( is_wp_compatible( '1.0' ) );
		self::assertFalse( is_wp_compatible( '99.99' ) );
	}
}
