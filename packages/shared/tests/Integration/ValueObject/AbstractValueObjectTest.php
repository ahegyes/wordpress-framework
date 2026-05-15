<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Shared\Tests\Integration\ValueObject;

use DeepWebSolutions\Framework\Shared\ValueObject\AbstractValueObject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

final readonly class FixtureValueObject extends AbstractValueObject {
	public function __construct(
		public string $name,
		public int $count,
	) {}
}

final readonly class OtherFixtureValueObject extends AbstractValueObject {
	public function __construct(
		public string $name,
	) {}
}

final readonly class WrapperFixtureValueObject extends AbstractValueObject {
	public function __construct(
		public FixtureValueObject $inner,
	) {}
}

#[CoversClass( AbstractValueObject::class )]
final class AbstractValueObjectTest extends TestCase {
	public function test_equals_returns_true_for_structurally_identical_objects(): void {
		$a = new FixtureValueObject( 'foo', 1 );
		$b = new FixtureValueObject( 'foo', 1 );
		self::assertTrue( $a->equals( $b ) );
	}

	public function test_equals_returns_false_for_different_property_values(): void {
		$a = new FixtureValueObject( 'foo', 1 );
		$b = new FixtureValueObject( 'foo', 2 );
		self::assertFalse( $a->equals( $b ) );
	}

	public function test_equals_returns_false_for_different_classes(): void {
		$a = new FixtureValueObject( 'foo', 1 );
		$b = new OtherFixtureValueObject( 'foo' );
		self::assertFalse( $a->equals( $b ) );
	}

	public function test_equals_recurses_into_nested_value_objects(): void {
		$a = new WrapperFixtureValueObject( new FixtureValueObject( 'foo', 1 ) );
		$b = new WrapperFixtureValueObject( new FixtureValueObject( 'foo', 1 ) );
		self::assertTrue( $a->equals( $b ) );
	}

	public function test_equals_returns_false_when_nested_value_objects_differ(): void {
		$a = new WrapperFixtureValueObject( new FixtureValueObject( 'foo', 1 ) );
		$b = new WrapperFixtureValueObject( new FixtureValueObject( 'foo', 2 ) );
		self::assertFalse( $a->equals( $b ) );
	}

	public function test_json_serialize_returns_public_properties(): void {
		$vo = new FixtureValueObject( 'foo', 7 );
		self::assertSame(
			array(
				'name'  => 'foo',
				'count' => 7,
			),
			$vo->jsonSerialize(),
		);
	}

	public function test_to_string_returns_json_form(): void {
		$vo = new FixtureValueObject( 'foo', 7 );
		self::assertSame( '{"name":"foo","count":7}', (string) $vo );
	}
}
