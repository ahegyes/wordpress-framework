<?php declare( strict_types=1 );

namespace Acme\PersonaShared;

use Acme\PersonaShared\Scoped\DeepWebSolutions\Framework\Shared\ValueObject\AbstractValueObject;

/**
 * A point on a plane, equal to any point with the same coordinates.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final readonly class Point extends AbstractValueObject {
	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   int $x The horizontal coordinate.
	 * @param   int $y The vertical coordinate.
	 */
	public function __construct(
		public int $x,
		public int $y
	) {}

	// endregion
}
