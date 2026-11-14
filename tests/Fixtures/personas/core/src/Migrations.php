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
	 * Stores each setting's default in an autoloaded row of its own and leaves a stored setting as it is.
	 *
	 * Each release that adds settings lists this step again, so that existing sites store the new defaults.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @throws  \RuntimeException Thrown when a default could not be stored.
	 *
	 * @return  void
	 */
	protected static function add_settings(): void {
		wp_prime_option_caches( \array_keys( Options::DEFAULTS ) );
		foreach ( Options::DEFAULTS as $key => $value ) {
			if ( false === get_option( $key ) && ! add_option( $key, $value, '', true ) ) {
				throw new \RuntimeException( "The option $key could not be stored." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing, never rendered.
			}
		}
	}

	// endregion
}
