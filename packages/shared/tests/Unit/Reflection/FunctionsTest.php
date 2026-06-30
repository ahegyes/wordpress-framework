<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Shared\Tests\Unit\Reflection;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\TestCase;

use function DeepWebSolutions\Framework\Shared\Reflection\convert_to_primitives;
use function DeepWebSolutions\Framework\Shared\Reflection\get_public_property_names;

enum FixtureColor: string {
	case Red  = 'red';
	case Blue = 'blue';
}

final class FixtureWithPublics implements \JsonSerializable {
	public function __construct(
		public string $name,
		public int $count,
	) {}

	/** @return array<string, mixed> */
	public function jsonSerialize(): array {
		return convert_to_primitives( $this );
	}
}

final class FixtureWithMixedVisibility {
	public string $public_value = 'visible';
	protected int $protected_value = 42;
	/** @phpstan-ignore property.onlyWritten */
	private string $private_value = 'secret';
}

final class FixtureEmpty implements \JsonSerializable {
	/** @return array<string, mixed> */
	public function jsonSerialize(): array {
		return convert_to_primitives( $this );
	}
}

final class FixtureWithEnum implements \JsonSerializable {
	public function __construct(
		public FixtureColor $color,
	) {}

	/** @return array<string, mixed> */
	public function jsonSerialize(): array {
		return convert_to_primitives( $this );
	}
}

final class FixtureWithDate implements \JsonSerializable {
	public function __construct(
		public \DateTimeImmutable $when,
	) {}

	/** @return array<string, mixed> */
	public function jsonSerialize(): array {
		return convert_to_primitives( $this );
	}
}

final class FixtureWithNested implements \JsonSerializable {
	public function __construct(
		public FixtureWithPublics $child,
	) {}

	/** @return array<string, mixed> */
	public function jsonSerialize(): array {
		return convert_to_primitives( $this );
	}
}

final class FixtureWithArray implements \JsonSerializable {
	/**
	 * @param array<int, mixed> $items
	 */
	public function __construct(
		public array $items,
	) {}

	/** @return array<string, mixed> */
	public function jsonSerialize(): array {
		return convert_to_primitives( $this );
	}
}

#[CoversFunction( 'DeepWebSolutions\Framework\Shared\Reflection\convert_to_primitives' )]
#[CoversFunction( 'DeepWebSolutions\Framework\Shared\Reflection\get_public_property_names' )]
final class FunctionsTest extends TestCase {
	public function test_get_public_property_names_returns_public_only(): void {
		$names = get_public_property_names( new FixtureWithMixedVisibility() );
		self::assertSame( array( 'public_value' ), $names );
	}

	public function test_get_public_property_names_returns_empty_array_for_no_publics(): void {
		$names = get_public_property_names( new FixtureEmpty() );
		self::assertSame( array(), $names );
	}

	public function test_get_public_property_names_returns_all_publics_in_declaration_order(): void {
		$names = get_public_property_names( new FixtureWithPublics( 'foo', 1 ) );
		self::assertSame( array( 'name', 'count' ), $names );
	}

	public function test_get_public_property_names_returns_same_names_across_instances(): void {
		$names_a = get_public_property_names( new FixtureWithPublics( 'a', 1 ) );
		$names_b = get_public_property_names( new FixtureWithPublics( 'b', 99 ) );
		self::assertSame( $names_a, $names_b );
	}

	public function test_convert_to_primitives_returns_public_property_values(): void {
		self::assertSame(
			array(
				'name'  => 'foo',
				'count' => 7,
			),
			convert_to_primitives( new FixtureWithPublics( 'foo', 7 ) ),
		);
	}

	public function test_convert_to_primitives_reduces_backed_enum_to_its_value(): void {
		self::assertSame(
			array( 'color' => 'red' ),
			convert_to_primitives( new FixtureWithEnum( FixtureColor::Red ) ),
		);
	}

	public function test_convert_to_primitives_formats_datetime_as_atom(): void {
		$when = new \DateTimeImmutable( '2026-05-15T12:34:56+00:00' );
		self::assertSame(
			array( 'when' => '2026-05-15T12:34:56+00:00' ),
			convert_to_primitives( new FixtureWithDate( $when ) ),
		);
	}

	public function test_convert_to_primitives_recurses_into_nested_json_serializable(): void {
		$nested = new FixtureWithNested( new FixtureWithPublics( 'inner', 3 ) );
		self::assertSame(
			array(
				'child' => array(
					'name'  => 'inner',
					'count' => 3,
				),
			),
			convert_to_primitives( $nested ),
		);
	}

	public function test_convert_to_primitives_recurses_into_arrays(): void {
		$obj = new FixtureWithArray( array( 'a', 'b', 'c' ) );
		self::assertSame(
			array( 'items' => array( 'a', 'b', 'c' ) ),
			convert_to_primitives( $obj ),
		);
	}

	public function test_convert_to_primitives_recurses_into_arrays_of_json_serializable(): void {
		$obj = new FixtureWithArray(
			array(
				new FixtureWithPublics( 'a', 1 ),
				new FixtureWithPublics( 'b', 2 ),
			),
		);
		self::assertSame(
			array(
				'items' => array(
					array(
						'name'  => 'a',
						'count' => 1,
					),
					array(
						'name'  => 'b',
						'count' => 2,
					),
				),
			),
			convert_to_primitives( $obj ),
		);
	}

	public function test_convert_to_primitives_returns_empty_for_no_public_properties(): void {
		self::assertSame(
			array(),
			convert_to_primitives( new FixtureEmpty() ),
		);
	}
}
