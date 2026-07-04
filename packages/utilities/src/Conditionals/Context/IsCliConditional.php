<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Conditionals\Context;

use DeepWebSolutions\Framework\Core\Conditional\ConditionalInterface;

/**
 * Pre-resolution gate that passes iff PHP is running under the CLI SAPI.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final readonly class IsCliConditional implements ConditionalInterface {
	// region MAGIC METHODS

	/**
	 * Constructs the conditional, capturing the SAPI name to compare against.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $sapi SAPI name to probe; defaults to the running interpreter's `PHP_SAPI`.
	 */
	public function __construct(
		protected string $sapi = \PHP_SAPI,
	) {}

	// endregion

	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function is_met(): bool {
		return 'cli' === $this->sapi;
	}

	// endregion
}
