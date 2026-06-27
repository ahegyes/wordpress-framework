<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Conditionals\Context;

use DeepWebSolutions\Framework\Core\Conditional\ConditionalInterface;

/**
 * Pre-resolution gate that passes iff PHP is running under the CLI SAPI.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class IsCliConditional implements ConditionalInterface {
	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function is_met(): bool {
		return 'cli' === \PHP_SAPI;
	}

	// endregion
}
