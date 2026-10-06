<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Unit\Shared\ValueObject;

use DeepWebSolutions\Framework\Shared\ValueObject\AbstractValueObject;
use DeepWebSolutions\Framework\Shared\ValueObject\ValueObjectInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

enum Currency {
	case Eur;
}

enum CurrencyCode: string {
	case Eur = 'EUR';
}

final readonly class Box extends AbstractValueObject {
	public function __construct(
		public mixed $content
	) {}
}

final readonly class Weight extends AbstractValueObject {
	public function __construct(
		public int $grams
	) {}
}

final readonly class Length extends AbstractValueObject {
	public function __construct(
		public int $millimetres
	) {}
}

final readonly class Sealed extends AbstractValueObject {
	public function __construct(
		protected int $shown,
		private int $hidden
	) {}
}

abstract readonly class Money extends AbstractValueObject {
	public function __construct(
		private int $cents
	) {}
}

final readonly class Euro extends Money {}

#[CoversClass( AbstractValueObject::class )]
final class AbstractValueObjectTest extends TestCase {
	public static function pairs(): array {
		$keyed = array(
			'a' => 1,
			'b' => 2,
		);

		return array(
			'dates-half-a-second-apart'           => array( new Box( new \DateTimeImmutable( '2026-01-01 12:00:00.000000 UTC' ) ), new Box( new \DateTimeImmutable( '2026-01-01 12:00:00.500000 UTC' ) ), false ),
			'same-instant-at-another-offset'      => array( new Box( new \DateTimeImmutable( '2026-01-01 12:00:00 UTC' ) ), new Box( new \DateTimeImmutable( '2026-01-01 14:00:00 +02:00' ) ), true ),
			'cases-of-two-enums'                  => array( new Box( Currency::Eur ), new Box( CurrencyCode::Eur ), false ),
			'case-and-its-backing-value'          => array( new Box( CurrencyCode::Eur ), new Box( 'EUR' ), false ),
			'nested-objects-of-two-classes'       => array( new Box( new Weight( 5 ) ), new Box( new Length( 5 ) ), false ),
			'arrays-of-equal-value-objects'       => array( new Box( array( new Weight( 5 ), new Weight( 6 ) ) ), new Box( array( new Weight( 5 ), new Weight( 6 ) ) ), true ),
			'reordered-keys'                      => array( new Box( $keyed ), new Box( \array_reverse( $keyed, true ) ), false ),
			'one-differing-scalar-element'        => array( new Box( $keyed ), new Box( \array_replace( $keyed, array( 'b' => 3 ) ) ), false ),
			'one-differing-value-object-element'  => array( new Box( array( 'a' => new Weight( 5 ) ) ), new Box( array( 'a' => new Weight( 6 ) ) ), false ),
			'equal-protected-and-private-values'  => array( new Sealed( 1, 2 ), new Sealed( 1, 2 ), true ),
			'differing-protected-property'        => array( new Sealed( 1, 2 ), new Sealed( 9, 2 ), false ),
			'differing-private-property'          => array( new Sealed( 1, 2 ), new Sealed( 1, 9 ), false ),
			'differing-private-parent-property'   => array( new Euro( 1 ), new Euro( 2 ), false ),
			'different-classes-with-equal-values' => array( new Weight( 5 ), new Length( 5 ), false ),
		);
	}

	#[DataProvider( 'pairs' )]
	public function test_equals_compares_every_property_by_value( ValueObjectInterface $left, ValueObjectInterface $right, bool $expected ): void {
		self::assertSame( $expected, $left->equals( $right ) );
		self::assertSame( $expected, $right->equals( $left ) );
	}
}
