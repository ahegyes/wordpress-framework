<?php declare( strict_types=1 );

namespace Acme\PersonaCore;

\defined( 'ABSPATH' ) || exit;

/**
 * Names the plugin's stored settings and their defaults, and reads each setting, guarding the stored value.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final class Options {
	// region FIELDS AND CONSTANTS

	/**
	 * The option that holds 'yes' where the site consents to uninstall removing the plugin's data.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     string
	 */
	public const string DELETE_DATA = 'acme_pc_delete_data';

	/**
	 * The option that holds the greeting.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     string
	 */
	public const string GREETING = 'acme_pc_greeting';

	/**
	 * The default of each option, in the form the option stores it.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     array<string, string>
	 */
	public const array DEFAULTS = array(
		self::DELETE_DATA => 'no',
		self::GREETING    => 'Hello',
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
	 * Returns an option as a string, or its default where no string is stored.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string $key The option's key.
	 *
	 * @return  string
	 */
	public static function get_string( string $key ): string {
		$default = self::DEFAULTS[ $key ] ?? '';
		$stored  = get_option( $key, $default );
		return \is_string( $stored ) ? $stored : $default;
	}

	// endregion
}
