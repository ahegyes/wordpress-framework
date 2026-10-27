<?php declare( strict_types=1 );

namespace Acme\PersonaShared;

use Acme\PersonaShared\Scoped\DeepWebSolutions\Framework\Shared\Error\ErrorInterface;

/**
 * The failure of a lookup whose key holds no value.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final readonly class UnknownKey implements ErrorInterface {
	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string $key The key the lookup asked for.
	 */
	public function __construct(
		public string $key
	) {}

	// endregion
}
