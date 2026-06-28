<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Shared\Tests\Unit\Version;

use DeepWebSolutions\Framework\Shared\ValueObject\AbstractValueObject;
use DeepWebSolutions\Framework\Shared\ValueObject\Exceptions\InvalidValueObjectException;
use DeepWebSolutions\Framework\Shared\Version\Version;
use DeepWebSolutions\Framework\Shared\Version\Exceptions\InvalidVersionException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesFunction;
use PHPUnit\Framework\TestCase;

#[CoversClass( Version::class )]
#[UsesClass( InvalidVersionException::class )]
#[UsesClass( InvalidValueObjectException::class )]
#[UsesClass( AbstractValueObject::class )]
#[UsesFunction( 'DeepWebSolutions\Framework\Shared\Reflection\get_public_property_names' )]
#[UsesFunction( 'DeepWebSolutions\Framework\Shared\Reflection\convert_to_primitives' )]
final class VersionTest extends TestCase {
	public function test_from_string_constructs_valid_semver(): void {
		$v = Version::from_string( '2.0.0' );
		self::assertSame( '2.0.0', $v->value );
	}

	public function test_from_string_accepts_two_component_version(): void {
		$v = Version::from_string( '2.0' );
		self::assertSame( '2.0', $v->value );
	}

	public function test_from_string_accepts_prerelease(): void {
		$v = Version::from_string( '2.0.0-beta.1' );
		self::assertSame( '2.0.0-beta.1', $v->value );
	}

	public function test_from_string_accepts_build_metadata(): void {
		$v = Version::from_string( '2.0.0+build.5' );
		self::assertSame( '2.0.0+build.5', $v->value );
	}

	public function test_from_string_throws_on_invalid(): void {
		$this->expectException( InvalidVersionException::class );
		Version::from_string( 'not-a-version' );
	}

	public function test_from_string_rejects_empty_prerelease_and_build_identifiers(): void {
		foreach ( array( '1.0.0-', '1.0.0-.', '1.0.0-alpha..1', '1.0.0+', '1.0.0+.' ) as $invalid ) {
			try {
				Version::from_string( $invalid );
				self::fail( "Expected InvalidVersionException for '{$invalid}'." );
			} catch ( InvalidVersionException ) {
				$this->addToAssertionCount( 1 );
			}
		}
	}

	public function test_from_parts_composes_only_supplied_components(): void {
		self::assertSame( '2', Version::from_parts( 2 )->value );
		self::assertSame( '2.0', Version::from_parts( 2, 0 )->value );
		self::assertSame( '2.0.0', Version::from_parts( 2, 0, 0 )->value );
		self::assertSame( '2.1.3-beta.1+build.5', Version::from_parts( 2, 1, 3, 'beta.1', 'build.5' )->value );
	}

	public function test_from_parts_throws_on_patch_without_minor(): void {
		$this->expectException( InvalidVersionException::class );
		Version::from_parts( 2, null, 0 );
	}

	public function test_from_parts_throws_on_invalid_composed_parts(): void {
		foreach ( array( array( -2 ), array( 2, 0, 0, 'beta..1' ), array( 2, 0, 0, null, '.' ) ) as $parts ) {
			try {
				Version::from_parts( ...$parts );
				self::fail( 'Expected InvalidVersionException for parts: ' . \json_encode( $parts ) );
			} catch ( InvalidVersionException ) {
				$this->addToAssertionCount( 1 );
			}
		}
	}

	public function test_is_greater_than(): void {
		self::assertTrue( Version::from_string( '2.0.1' )->is_greater_than( Version::from_string( '2.0.0' ) ) );
		self::assertFalse( Version::from_string( '2.0.0' )->is_greater_than( Version::from_string( '2.0.1' ) ) );
		self::assertFalse( Version::from_string( '2.0.0' )->is_greater_than( Version::from_string( '2.0.0' ) ) );
	}

	public function test_is_less_than(): void {
		self::assertTrue( Version::from_string( '1.9.0' )->is_less_than( Version::from_string( '2.0.0' ) ) );
		self::assertFalse( Version::from_string( '2.0.0' )->is_less_than( Version::from_string( '1.9.0' ) ) );
	}

	public function test_is_equal_to(): void {
		self::assertTrue( Version::from_string( '2.0.0' )->is_equal_to( Version::from_string( '2.0.0' ) ) );
		self::assertFalse( Version::from_string( '2.0.0' )->is_equal_to( Version::from_string( '2.0.1' ) ) );
	}

	public function test_is_equal_to_follows_version_compare_semantics(): void {
		// Numerically equivalent segments match even when spelled differently.
		self::assertTrue( Version::from_string( '1.0' )->is_equal_to( Version::from_string( '1.00' ) ) );
		// Omitted components are not padded: 2.0 compares below 2.0.0.
		self::assertFalse( Version::from_string( '2.0' )->is_equal_to( Version::from_string( '2.0.0' ) ) );
		// Build metadata participates in the comparison; SemVer would ignore it.
		self::assertFalse( Version::from_string( '1.0.0+build.5' )->is_equal_to( Version::from_string( '1.0.0' ) ) );
	}

	public function test_string_conversion_returns_version_string(): void {
		// Overrides the base class JSON form: a version's natural string is itself.
		self::assertSame( '2.0.0-beta.1', (string) Version::from_string( '2.0.0-beta.1' ) );
	}

	public function test_equals_inherited_from_abstract_value_object(): void {
		$a = Version::from_string( '2.0.0' );
		$b = Version::from_string( '2.0.0' );
		self::assertTrue( $a->equals( $b ) );
	}

	public function test_invalid_version_exception_is_a_value_object_exception_naming_the_type(): void {
		try {
			Version::from_string( 'not-a-version' );
			self::fail( 'Expected InvalidVersionException.' );
		} catch ( InvalidVersionException $e ) {
			self::assertInstanceOf( InvalidValueObjectException::class, $e );
			self::assertStringContainsString( 'Version', $e->getMessage() );
		}
	}
}
