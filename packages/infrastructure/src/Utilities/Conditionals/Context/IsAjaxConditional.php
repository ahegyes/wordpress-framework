<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Conditionals\Context;

use DeepWebSolutions\Framework\Core\Conditional\ConditionalInterface;

/**
 * Pre-resolution gate that passes iff the current request is an admin-ajax request.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class IsAjaxConditional implements ConditionalInterface {
	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function is_met(): bool {
		return \wp_doing_ajax();
	}

	// endregion
}
