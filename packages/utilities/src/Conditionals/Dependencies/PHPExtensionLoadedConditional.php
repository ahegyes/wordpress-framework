<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Conditionals\Dependencies;

use DeepWebSolutions\Framework\Core\Conditional\ConditionalInterface;

/**
 * Pre-resolution gate that passes iff the named PHP extension is loaded.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class PHPExtensionLoadedConditional implements ConditionalInterface {
	// region MAGIC METHODS

	/**
	 * Constructs the conditional with the extension name to probe.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $extension PHP extension name (e.g., `json`, `mbstring`).
	 */
	public function __construct(
		private readonly string $extension,
	) {}

	// endregion

	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function is_met(): bool {
		return \extension_loaded( $this->extension );
	}

	// endregion
}
