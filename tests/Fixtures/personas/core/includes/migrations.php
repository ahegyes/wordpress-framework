<?php declare( strict_types=1 );
/**
 * Holds the plugin's migration steps, which bring its stored data to the revision its code expects.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @package Acme\PersonaCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns the migration steps, append-only, where index 0 brings the data to revision 1.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @return  list<callable(): void>
 */
function acme_pc_get_migration_steps(): array {
	return array(
		'acme_pc_add_settings',
	);
}

/**
 * Stores the plugin's settings with their defaults.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @throws  RuntimeException Thrown when the settings could not be stored.
 *
 * @return  void
 */
function acme_pc_add_settings(): void {
	if ( false === get_option( ACME_PC_OPTION_SETTINGS ) && ! add_option( ACME_PC_OPTION_SETTINGS, array( 'greeting' => 'Hello' ) ) ) {
		throw new RuntimeException( 'The settings could not be stored.' );
	}
}
