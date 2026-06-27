<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Conditionals\Dependencies;

use DeepWebSolutions\Framework\Core\Conditional\ConditionalInterface;

/**
 * Pre-resolution gate that passes iff the named PHP function exists.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class PHPFunctionExistsConditional implements ConditionalInterface {
	// region MAGIC METHODS

	/**
	 * Constructs the conditional with the function name to probe.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $function_name Fully-qualified function name to check.
	 */
	public function __construct(
		protected readonly string $function_name,
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
		return \function_exists( $this->function_name );
	}

	// endregion
}
