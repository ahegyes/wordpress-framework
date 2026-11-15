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

add_action(
	'admin_notices',
	static function (): void {
		Requirements::print_admin_notice( esc_html__( 'Acme Persona Bootstrap is running.', 'acme-persona-bootstrap' ), array( 'type' => 'info' ) );
	}
);
