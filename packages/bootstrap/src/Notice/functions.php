<?php

namespace DeepWebSolutions\Framework\Bootstrap\Notice;

use function DeepWebSolutions\Framework\Bootstrap\Plugin\get_plugin_metadata;

/**
 * Hooks an admin notice rendering the unmet-requirements `WP_Error`. No-op
 * when the error bag is empty.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string    $plugin_basename Plugin file path relative to plugins dir.
 * @param   \WP_Error $error           Result returned by `check_requirements()`.
 *
 * @return  void
 */
function output_requirements_error( $plugin_basename, \WP_Error $error ) {
	if ( ! $error->has_errors() ) {
		return;
	}

	\add_action(
		'admin_notices',
		function () use ( $plugin_basename, $error ) {
			$metadata = get_plugin_metadata( $plugin_basename, true );

			$name    = isset( $metadata['Name'] ) && '' !== $metadata['Name'] ? $metadata['Name'] : $plugin_basename;
			$version = isset( $metadata['Version'] ) ? $metadata['Version'] : '';

			$intro = \sprintf(
				/* translators: 1: plugin name, 2: plugin version */
				\__( '<strong>%1$s (version %2$s)</strong> could not be initialized. Your environment does not meet all the requirements:', 'wp-framework-bootstrap' ),
				\esc_html( $name ),
				\esc_html( $version )
			);

			$items = array();

			$php_data = $error->get_error_data( 'plugin_php_incompatible' );
			if ( \is_array( $php_data ) ) {
				$items[] = \sprintf(
					/* translators: 1: minimum PHP version, 2: current PHP version */
					\__( 'Requires PHP %1$s or higher; you are running %2$s.', 'wp-framework-bootstrap' ),
					\esc_html( isset( $php_data['min'] ) ? $php_data['min'] : '' ),
					\esc_html( isset( $php_data['current'] ) ? $php_data['current'] : '' )
				);
			}

			$wp_data = $error->get_error_data( 'plugin_wp_incompatible' );
			if ( \is_array( $wp_data ) ) {
				$items[] = \sprintf(
					/* translators: 1: minimum WordPress version, 2: current WordPress version */
					\__( 'Requires WordPress %1$s or higher; you are running %2$s.', 'wp-framework-bootstrap' ),
					\esc_html( isset( $wp_data['min'] ) ? $wp_data['min'] : '' ),
					\esc_html( isset( $wp_data['current'] ) ? $wp_data['current'] : '' )
				);
			}

			if ( array() === $items ) {
				return;
			}

			$message = $intro . '<ul class="ul-disc"><li>' . \implode( '</li><li>', $items ) . '</li></ul>';
			if ( \function_exists( '\wp_admin_notice' ) ) {
				\wp_admin_notice( $message, array( 'type' => 'error' ) );
			} else {
				echo \wp_kses_post( '<div class="notice notice-error"><p>' . $message . '</p></div>' );
			}
		}
	);
}
