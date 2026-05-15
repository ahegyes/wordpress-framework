<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Shared\ValueObject;

/**
 * Contract for a value object: structural equality + JSON serialization + string form.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
interface ValueObjectInterface extends \JsonSerializable, \Stringable {
	/**
	 * Returns whether this value object is structurally equal to another.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   ValueObjectInterface $other The value object to compare against.
	 *
	 * @return  bool
	 */
	public function equals( ValueObjectInterface $other ): bool;
}
