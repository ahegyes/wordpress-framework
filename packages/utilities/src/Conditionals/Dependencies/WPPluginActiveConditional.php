<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Conditionals\Dependencies;

use DeepWebSolutions\Framework\Core\Conditional\ConditionalInterface;

/**
 * Pre-resolution gate that passes iff the named WordPress plugin is active.
 * Plugin identifier is the basename form: `plugin-slug/plugin-slug.php`.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class WPPluginActiveConditional implements ConditionalInterface {
	// region MAGIC METHODS

	/**
	 * Constructs the conditional with the plugin basename to probe.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $plugin_basename WordPress plugin basename (e.g., `woocommerce/woocommerce.php`).
	 */
	public function __construct(
		protected readonly string $plugin_basename,
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
		if ( ! \function_exists( 'is_plugin_active' ) ) {
			require_once \ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return \is_plugin_active( $this->plugin_basename );
	}

	// endregion
}
