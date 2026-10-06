<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\PHPStan\Shared;

use DeepWebSolutions\Framework\Shared\ValueObject\AbstractValueObject;

final readonly class Duration extends AbstractValueObject {
	public function __construct(
		public int $seconds
	) {}
}

final readonly class Distance extends AbstractValueObject {
	public function __construct(
		public int $metres
	) {}
}

final class ValueObjectTypes {
	public static function compare_within_a_class( Duration $duration ): bool {
		return $duration->equals( new Duration( 60 ) );
	}

	public static function compare_across_classes( Duration $duration, Distance $distance ): bool {
		return $duration->equals( $distance ); // @phpstan-ignore argument.type
	}
}
