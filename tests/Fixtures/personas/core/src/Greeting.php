<?php declare( strict_types=1 );

namespace Acme\PersonaCore;

use Acme\PersonaCore\Scoped\DeepWebSolutions\Framework\Core\ComponentInterface;

\defined( 'ABSPATH' ) || exit;

/**
 * Adds the greeting shortcode, which prints the greeting the plugin's settings hold.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final class Greeting implements ComponentInterface {
	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	#[\Override]
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register_shortcode' ) );
	}

	// endregion

	// region HOOKS

	/**
	 * Registers the greeting shortcode.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function register_shortcode(): void {
		add_shortcode( Plugin::PREFIX . '_greeting', array( $this, 'render' ) );
	}

	/**
	 * Returns the greeting the plugin's settings hold.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  string
	 */
	public function render(): string {
		$settings = get_option( Plugin::PREFIX . '_settings' );

		return esc_html( \is_array( $settings ) && \is_string( $settings['greeting'] ?? null ) ? $settings['greeting'] : '' );
	}

	// endregion
}
