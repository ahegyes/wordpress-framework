<?php declare( strict_types=1 );
/**
 * Shows the plugin's admin notice.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @package Acme\PersonaBootstrap
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_notices', 'acme_pb_render_notice' );

/**
 * Prints the notice that tells administrators the plugin runs.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @return  void
 */
function acme_pb_render_notice(): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	printf( '<div class="notice notice-info"><p>%s</p></div>', esc_html__( 'Acme Persona Bootstrap is running.', 'acme-persona-bootstrap' ) );
}
