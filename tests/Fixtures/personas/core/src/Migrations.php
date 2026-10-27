<?php declare( strict_types=1 );

namespace Acme\PersonaCore;

\defined( 'ABSPATH' ) || exit;

/**
 * Holds the plugin's migration steps, which bring its stored data to the revision its code expects.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final class Migrations {
	// region FIELDS AND CONSTANTS

	/**
	 * The migration steps, append-only, where index 0 brings the data to revision 1.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     list<callable(): void>
	 */
	public const array STEPS = array(
		array( self::class, 'add_settings' ),
	);

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	protected function __construct() {}

	// endregion

	// region METHODS

	/**
	 * Stores the plugin's settings with the greeting's default.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @throws  \RuntimeException Thrown when the settings could not be stored.
	 *
	 * @return  void
	 */
	public static function add_settings(): void {
		if ( false === get_option( Plugin::PREFIX . '_settings' ) && ! add_option( Plugin::PREFIX . '_settings', array( 'greeting' => 'Hello' ) ) ) {
			throw new \RuntimeException( 'The settings could not be stored.' );
		}
	}

	// endregion
}
