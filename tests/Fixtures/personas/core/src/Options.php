<?php declare( strict_types=1 );

namespace Acme\PersonaCore;

\defined( 'ABSPATH' ) || exit;

/**
 * Names the plugin's stored settings and their defaults, and reads them.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final class Options {
	// region FIELDS AND CONSTANTS

	/**
	 * The option that holds the plugin's settings: the greeting, and 'yes' under delete_data where the site consents to uninstall removing the plugin's data.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     string
	 */
	public const string SETTINGS = Plugin::PREFIX . '_settings';

	/**
	 * The settings the plugin stores on its first migration.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     array{greeting: string}
	 */
	public const array DEFAULTS = array( 'greeting' => 'Hello' );

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
	 * Returns the stored greeting, or the default one where none is stored.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  string
	 */
	public static function get_greeting(): string {
		$settings = get_option( self::SETTINGS, self::DEFAULTS );

		return \is_array( $settings ) && \is_string( $settings['greeting'] ?? null ) ? $settings['greeting'] : self::DEFAULTS['greeting'];
	}

	// endregion
}
