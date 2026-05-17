<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Bootstrap\Tests\Unit;

use PHPUnit\Framework\TestCase;

use function DeepWebSolutions\Framework\Bootstrap\Environment\is_php_compatible;
use function DeepWebSolutions\Framework\Bootstrap\Environment\is_wp_compatible;

final class IsCompatibleTest extends TestCase {
	public function test_is_php_compatible_falls_back_to_version_compare_when_wp_native_missing(): void {
		self::assertFalse( \function_exists( '\is_php_version_compatible' ), 'WP must not be loaded for unit tests.' );

		self::assertTrue( is_php_compatible( '5.0' ) );
		self::assertFalse( is_php_compatible( '99.99' ) );
	}

	public function test_is_wp_compatible_falls_back_to_version_compare_when_wp_native_missing(): void {
		self::assertFalse( \function_exists( '\is_wp_version_compatible' ), 'WP must not be loaded for unit tests.' );

		$GLOBALS['wp_version'] = '6.5';
		try {
			self::assertTrue( is_wp_compatible( '1.0' ) );
			self::assertTrue( is_wp_compatible( '6.5' ) );
			self::assertFalse( is_wp_compatible( '99.99' ) );
		} finally {
			unset( $GLOBALS['wp_version'] );
		}
	}

	public function test_is_wp_compatible_strips_beta_suffix_in_fallback(): void {
		self::assertFalse( \function_exists( '\is_wp_version_compatible' ), 'WP must not be loaded for unit tests.' );

		$GLOBALS['wp_version'] = '6.5-RC1';
		try {
			self::assertTrue( is_wp_compatible( '6.5' ) );
			self::assertFalse( is_wp_compatible( '6.6' ) );
		} finally {
			unset( $GLOBALS['wp_version'] );
		}
	}

	public function test_is_wp_compatible_treats_missing_wp_version_global_as_zero(): void {
		self::assertFalse( \function_exists( '\is_wp_version_compatible' ), 'WP must not be loaded for unit tests.' );
		self::assertArrayNotHasKey( 'wp_version', $GLOBALS );

		self::assertFalse( is_wp_compatible( '1.0' ) );
	}
}
