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
	 * Returns the migration steps, append-only, where index 0 brings the data to revision 1.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  list<callable(): void>
	 */
	public static function get_steps(): array {
		return array(
			self::add_settings( ... ),
		);
	}

	// endregion

	// region HELPERS

	/**
	 * Stores the plugin's settings with their defaults.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @throws  \RuntimeException Thrown when the settings could not be stored.
	 *
	 * @return  void
	 */
	protected static function add_settings(): void {
		if ( false === get_option( Options::SETTINGS ) && ! add_option( Options::SETTINGS, Options::DEFAULTS ) ) {
			throw new \RuntimeException( 'The settings could not be stored.' );
		}
	}

	// endregion
}
