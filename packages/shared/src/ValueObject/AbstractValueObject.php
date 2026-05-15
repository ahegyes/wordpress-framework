<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Shared\ValueObject;

use function DeepWebSolutions\Framework\Shared\Reflection\convert_to_primitives;
use function DeepWebSolutions\Framework\Shared\Reflection\get_public_property_names;

/**
 * Base class for value objects. Provides reflection-driven structural equality,
 * JSON serialization, and a string form derived from the JSON representation.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
abstract readonly class AbstractValueObject implements ValueObjectInterface {
	// region MAGIC METHODS

	/**
	 * Returns the value object's JSON form; falls back to the encoder's error message on failure.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  string
	 */
	public function __toString(): string {
		try {
			$json = \wp_json_encode( $this, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE );
			return false === $json ? '' : $json;
		} catch ( \JsonException $exception ) {
			return $exception->getMessage();
		}
	}

	// endregion

	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	final public function equals( ValueObjectInterface $other ): bool {
		if ( \get_class( $other ) !== \get_class( $this ) ) {
			return false;
		}

		foreach ( get_public_property_names( $this ) as $property_name ) {
			// @phpstan-ignore-next-line property.dynamicName
			$value_this = $this->{ $property_name };
			// @phpstan-ignore-next-line property.dynamicName
			$value_other = $other->{ $property_name };

			if ( $value_this instanceof ValueObjectInterface ) {
				if ( ! $value_other instanceof ValueObjectInterface || ! $value_this->equals( $value_other ) ) {
					return false;
				}
			} elseif ( $value_this !== $value_other ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  array<string, mixed>
	 */
	final public function jsonSerialize(): array {
		return convert_to_primitives( $this );
	}

	// endregion
}
