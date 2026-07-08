<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Conditionals\Dependencies;

use DeepWebSolutions\Framework\Core\Conditional\ConditionalInterface;
use DeepWebSolutions\Framework\Shared\Version\Version;

/**
 * Pre-resolution gate that passes iff the named WordPress plugin is active at or above the
 * minimum version. Plugin identifier is the basename form: `plugin-slug/plugin-slug.php`.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final readonly class WPPluginVersionConditional implements ConditionalInterface {
	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string  $plugin_basename WordPress plugin basename (e.g., `woocommerce/woocommerce.php`).
	 * @param   Version $minimum         Minimum plugin version that satisfies the gate.
	 */
	public function __construct(
		protected string $plugin_basename,
		protected Version $minimum,
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
		// is_plugin_active() and get_plugin_data() both live in the same admin include.
		if ( ! \function_exists( 'is_plugin_active' ) ) {
			require_once \ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! \is_plugin_active( $this->plugin_basename ) ) {
			return false;
		}

		// An unreadable main file cannot yield a version header; bail before get_plugin_data()
		// warns on it, keeping is_met() total like the other version conditionals.
		$plugin_file = \WP_PLUGIN_DIR . '/' . $this->plugin_basename;
		if ( ! \is_readable( $plugin_file ) ) {
			return false;
		}

		// Compare the raw header string rather than parsing it into a Version: third-party plugins
		// use version shapes the Version grammar rejects (four segments, undashed suffixes), and
		// is_met() must not throw. A missing Version header is unmet — the minimum is unprovable.
		$version = \get_plugin_data( $plugin_file, false, false )['Version'] ?? '';

		return \is_string( $version ) && '' !== $version
			&& \version_compare( $version, $this->minimum->value, '>=' );
	}

	// endregion
}
