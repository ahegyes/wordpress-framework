<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Shared\ValueObject;

/**
 * A value compared by what it holds, not by its identity.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
interface ValueObjectInterface {
	/**
	 * Returns whether another value object of the same class holds equal values.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   static $other The value object to compare with.
	 *
	 * @return  bool
	 */
	public function equals( self $other ): bool;
}
