<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Integration\Conditionals\Dependencies;

use DeepWebSolutions\Framework\Shared\Version\Version;
use DeepWebSolutions\Framework\Utilities\Conditionals\Dependencies\WPVersionConditional;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( WPVersionConditional::class )]
#[UsesClass( Version::class )]
final class WPVersionConditionalTest extends TestCase {
	public function test_meets_lower_minimum(): void {
		$conditional = new WPVersionConditional( Version::from_string( '1.0' ) );

		self::assertTrue( $conditional->is_met() );
	}

	public function test_fails_unreachable_minimum(): void {
		$conditional = new WPVersionConditional( Version::from_string( '999.0.0' ) );

		self::assertFalse( $conditional->is_met() );
	}

	public function test_prerelease_satisfies_its_release_minimum(): void {
		$this->with_wp_version(
			'6.8-RC1',
			static fn () => self::assertTrue( ( new WPVersionConditional( Version::from_string( '6.8' ) ) )->is_met() ),
		);
	}

	public function test_prerelease_below_minimum_still_fails(): void {
		$this->with_wp_version(
			'6.7-RC1',
			static fn () => self::assertFalse( ( new WPVersionConditional( Version::from_string( '6.8' ) ) )->is_met() ),
		);
	}

	public function test_two_part_current_satisfies_three_part_zero_minimum(): void {
		$this->with_wp_version(
			'7.0',
			static fn () => self::assertTrue( ( new WPVersionConditional( Version::from_string( '7.0.0' ) ) )->is_met() ),
		);
	}

	public function test_empty_wp_version_returns_false_without_throwing(): void {
		$this->with_wp_version(
			'',
			static fn () => self::assertFalse( ( new WPVersionConditional( Version::from_string( '6.8' ) ) )->is_met() ),
		);
	}

	public function test_four_segment_wp_version_does_not_throw(): void {
		$this->with_wp_version(
			'6.8.1.2',
			static fn () => self::assertTrue( ( new WPVersionConditional( Version::from_string( '6.8' ) ) )->is_met() ),
		);
	}

	/**
	 * @param callable():void $assertion
	 */
	private function with_wp_version( string $version, callable $assertion ): void {
		global $wp_version;
		$original   = $wp_version;
		$wp_version = $version;

		try {
			$assertion();
		} finally {
			$wp_version = $original;
		}
	}
}
