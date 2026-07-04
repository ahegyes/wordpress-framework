<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Conditionals\Context;

use DeepWebSolutions\Framework\Core\Conditional\ConditionalInterface;

/**
 * Pre-resolution gate that passes iff the current user has the given capability.
 *
 * A coarse attach-time gate: it is evaluated at plugins_loaded, before other plugins'
 * capability filters have settled, so a privileged action must re-check the capability
 * inside the hook callback rather than rely on this gate.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final readonly class CurrentUserCanConditional implements ConditionalInterface {
	// region MAGIC METHODS

	/**
	 * Constructs the conditional with the capability to probe.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $capability WordPress capability slug (e.g., `manage_options`).
	 */
	public function __construct(
		protected string $capability,
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
		return \current_user_can( $this->capability );
	}

	// endregion
}
