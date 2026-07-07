<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Shared\Tests\Integration\ValueObject;

use DeepWebSolutions\Framework\Shared\ValueObject\AbstractValueObject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesFunction;
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

final readonly class CollectionFixtureValueObject extends AbstractValueObject {
	/**
	 * @param list<FixtureValueObject> $items
	 */
	public function __construct(
		public array $items,
	) {}
}

enum FixtureStatus: string {
	case Active   = 'active';
	case Inactive = 'inactive';
}

final readonly class EnumFixtureValueObject extends AbstractValueObject {
	public function __construct(
		public FixtureStatus $status,
	) {}
}

final readonly class DateFixtureValueObject extends AbstractValueObject {
	public function __construct(
		public \DateTimeImmutable $moment,
	) {}
}

#[CoversClass( AbstractValueObject::class )]
#[UsesFunction( 'DeepWebSolutions\Framework\Shared\Reflection\get_public_property_names' )]
#[UsesFunction( 'DeepWebSolutions\Framework\Shared\Reflection\convert_to_primitives' )]
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

	public function test_equals_recurses_into_arrays_of_value_objects(): void {
		$a = new CollectionFixtureValueObject(
			array(
				new FixtureValueObject( 'foo', 1 ),
				new FixtureValueObject( 'bar', 2 ),
			),
		);
		$b = new CollectionFixtureValueObject(
			array(
				new FixtureValueObject( 'foo', 1 ),
				new FixtureValueObject( 'bar', 2 ),
			),
		);

		self::assertTrue( $a->equals( $b ) );
		self::assertSame( $a->jsonSerialize(), $b->jsonSerialize() );
	}

	public function test_equals_false_when_value_object_inside_array_differs(): void {
		$a = new CollectionFixtureValueObject(
			array(
				new FixtureValueObject( 'foo', 1 ),
				new FixtureValueObject( 'bar', 2 ),
			),
		);
		$b = new CollectionFixtureValueObject(
			array(
				new FixtureValueObject( 'foo', 1 ),
				new FixtureValueObject( 'bar', 3 ),
			),
		);

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

	public function test_json_serialize_reduces_backed_enum_to_scalar_value(): void {
		$vo = new EnumFixtureValueObject( FixtureStatus::Active );
		self::assertSame(
			array( 'status' => 'active' ),
			$vo->jsonSerialize(),
		);
	}

	public function test_equals_true_for_same_backed_enum_case(): void {
		$a = new EnumFixtureValueObject( FixtureStatus::Active );
		$b = new EnumFixtureValueObject( FixtureStatus::Active );
		self::assertTrue( $a->equals( $b ) );
	}

	public function test_equals_false_for_different_backed_enum_case(): void {
		$a = new EnumFixtureValueObject( FixtureStatus::Active );
		$b = new EnumFixtureValueObject( FixtureStatus::Inactive );
		self::assertFalse( $a->equals( $b ) );
	}

	public function test_json_serialize_formats_datetime_as_atom_string(): void {
		$vo = new DateFixtureValueObject( new \DateTimeImmutable( '2026-06-30T12:34:56+00:00' ) );
		self::assertSame(
			array( 'moment' => '2026-06-30T12:34:56+00:00' ),
			$vo->jsonSerialize(),
		);
	}

	public function test_equals_true_for_distinct_datetime_instances_at_same_instant(): void {
		$a = new DateFixtureValueObject( new \DateTimeImmutable( '2026-06-30T12:34:56+00:00' ) );
		$b = new DateFixtureValueObject( new \DateTimeImmutable( '2026-06-30T12:34:56+00:00' ) );
		self::assertTrue( $a->equals( $b ) );
	}
}
