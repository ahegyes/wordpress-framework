<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Shared\ValueObject;

use function DeepWebSolutions\Framework\Shared\Reflection\convert_to_primitives;

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
	 * Returns the value object's JSON form; a {@see \JsonException} from the encoder
	 * yields the exception's message, and a false return yields an empty string.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  string
	 */
	#[\Override]
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
	#[\Override]
	final public function equals( ValueObjectInterface $other ): bool {
		if ( $other::class !== $this::class ) {
			return false;
		}

		return convert_to_primitives( $this ) === convert_to_primitives( $other );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  array<string, mixed>
	 */
	#[\Override]
	final public function jsonSerialize(): array {
		return convert_to_primitives( $this );
	}

	// endregion
}
