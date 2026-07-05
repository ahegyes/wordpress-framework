<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Unit\Conditionals\Dependencies;

use DeepWebSolutions\Framework\Shared\Version\Version;
use DeepWebSolutions\Framework\Utilities\Conditionals\Dependencies\PHPVersionConditional;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( PHPVersionConditional::class )]
#[UsesClass( Version::class )]
final class PHPVersionConditionalTest extends TestCase {
	public function test_meets_lower_minimum(): void {
		self::assertTrue( ( new PHPVersionConditional( Version::from_string( '5.6' ) ) )->is_met() );
	}

	public function test_fails_unreachable_minimum(): void {
		self::assertFalse( ( new PHPVersionConditional( Version::from_string( '999.0.0' ) ) )->is_met() );
	}

	public function test_exact_current_version_is_met(): void {
		// version_compare ranks a pre-release below its release, so an exact match only holds on
		// a release build; skip on RC/dev runtimes where PHP_EXTRA_VERSION is set.
		if ( '' !== \PHP_EXTRA_VERSION ) {
			self::markTestSkipped( 'Exact-version equality only holds on release builds.' );
		}

		$current = Version::from_parts( \PHP_MAJOR_VERSION, \PHP_MINOR_VERSION, \PHP_RELEASE_VERSION );

		self::assertTrue( ( new PHPVersionConditional( $current ) )->is_met() );
	}
}
