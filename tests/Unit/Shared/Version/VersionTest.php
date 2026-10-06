<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Unit\Shared\Version;

use DeepWebSolutions\Framework\Shared\Exception\InvalidArgumentException;
use DeepWebSolutions\Framework\Shared\Version\Version;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( Version::class )]
final class VersionTest extends TestCase {
	public static function canonical_forms(): array {
		return array(
			'two-segments'                  => array( '2.0', '2.0.0' ),
			'uppercase-label-without-a-dot' => array( '7.1-RC1', '7.1.0-rc.1' ),
			'numbered-pre-release'          => array( '2.0.0-beta.1', '2.0.0-beta.1' ),
			'pre-release-numbered-zero'     => array( '2.0.0-beta.0', '2.0.0-beta' ),
			'patch-release'                 => array( '2.0.1', '2.0.1' ),
		);
	}

	#[DataProvider( 'canonical_forms' )]
	public function test_a_valid_version_prints_in_canonical_form( string $version, string $canonical ): void {
		self::assertSame( $canonical, (string) Version::from_string( $version ) );
	}

	public static function invalid_versions(): array {
		return array(
			'single-segment'         => array( '2' ),
			'four-segments'          => array( '1.2.3.4' ),
			'label-without-a-hyphen' => array( '8.5.0RC1' ),
			'v-prefix'               => array( 'v1.2.3' ),
			'build-metadata'         => array( '1.0.0+build.5' ),
			'unknown-label'          => array( '1.0.0-pre.1' ),
			'leading-zero'           => array( '01.2.3' ),
			'dot-without-a-number'   => array( '2.0.0-beta.' ),
			'trailing-newline'       => array( "1.2.3\n" ),
		);
	}

	#[DataProvider( 'invalid_versions' )]
	public function test_from_string_rejects_an_invalid_version( string $version ): void {
		$this->expectException( InvalidArgumentException::class );

		Version::from_string( $version );
	}

	public static function equivalent_spellings(): array {
		return array(
			'omitted-patch'              => array( '2.0', '2.0.0' ),
			'number-without-a-dot'       => array( '2.0.0-beta1', '2.0.0-beta.1' ),
			'omitted-pre-release-number' => array( '2.0.0-beta', '2.0.0-beta.0' ),
		);
	}

	#[DataProvider( 'equivalent_spellings' )]
	public function test_equivalent_spellings_are_equal( string $left, string $right ): void {
		self::assertTrue( Version::from_string( $left )->equals( Version::from_string( $right ) ) );
		self::assertSame( 0, Version::from_string( $left )->compare( Version::from_string( $right ) ) );
	}

	public function test_compare_orders_versions_by_precedence(): void {
		$ascending = array( '1.9.0', '1.10.0', '2.0.0-dev', '2.0.0-alpha', '2.0.0-beta', '2.0.0-beta.2', '2.0.0-rc', '2.0.0', '2.0.1' );

		foreach ( $ascending as $lower_index => $lower ) {
			foreach ( \array_slice( $ascending, $lower_index + 1 ) as $higher ) {
				self::assertSame( -1, Version::from_string( $lower )->compare( Version::from_string( $higher ) ), "$lower < $higher" );
				self::assertSame( 1, Version::from_string( $higher )->compare( Version::from_string( $lower ) ), "$higher > $lower" );
			}
		}
	}

	public function test_equals_holds_exactly_when_compare_is_zero(): void {
		$versions = array( '2.0', '2.0.0', '2.0.1', '2.0.0-beta', '2.0.0-beta.0', '2.0.0-beta1', '2.0.0-beta.1', '7.1-RC1', '7.1.0-rc.1' );

		foreach ( $versions as $left ) {
			foreach ( $versions as $right ) {
				$version = Version::from_string( $left );
				$other   = Version::from_string( $right );

				self::assertSame( 0 === $version->compare( $other ), $version->equals( $other ), "$left vs $right" );
			}
		}
	}
}
