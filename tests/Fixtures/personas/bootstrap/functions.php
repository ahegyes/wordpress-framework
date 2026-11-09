<?php declare( strict_types=1 );
/**
 * Shows the plugin's admin notice.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @package Acme\PersonaBootstrap
 */

use Acme\PersonaBootstrap\Scoped\DeepWebSolutions\Framework\Bootstrap\Requirements;

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
	Requirements::print_admin_notice( esc_html__( 'Acme Persona Bootstrap is running.', 'acme-persona-bootstrap' ), array( 'type' => 'info' ) );
}
