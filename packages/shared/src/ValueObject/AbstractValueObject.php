<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Shared\ValueObject;

/**
 * Optional base that compares every property of two value objects of the same class.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
abstract readonly class AbstractValueObject implements ValueObjectInterface {
	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	final public function equals( ValueObjectInterface $other ): bool {
		// An array cast holds every property, including the private ones of parent classes.
		return static::class === $other::class && self::are_equal( (array) $this, (array) $other );
	}

	// endregion

	// region HELPERS

	/**
	 * Returns whether two property values are equal, comparing nested value objects with equals(), dates by instant to the microsecond, arrays by keys, order and values, and anything else with ===.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed $value The property's value on this object.
	 * @param   mixed $other The property's value on the other object.
	 *
	 * @return  bool
	 */
	protected static function are_equal( mixed $value, mixed $other ): bool {
		return match ( true ) {
			$value instanceof ValueObjectInterface => $other instanceof ValueObjectInterface && $value->equals( $other ),
			$value instanceof \DateTimeInterface   => $other instanceof \DateTimeInterface && $value->format( 'U.u' ) === $other->format( 'U.u' ),
			\is_array( $value )                    => \is_array( $other ) && \array_keys( $value ) === \array_keys( $other )
				&& \array_all( $value, static fn ( mixed $item, int|string $key ): bool => self::are_equal( $item, $other[ $key ] ) ),
			default                                => $value === $other,
		};
	}

	// endregion
}
