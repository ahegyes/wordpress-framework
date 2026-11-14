<?php declare( strict_types=1 );

namespace Acme\ConsumerA;

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
	public const string DELETE_DATA = 'acme_ca_delete_data';

	/**
	 * The option that holds 'yes' where the site switches the reports on.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     string
	 */
	public const string REPORTS = 'acme_ca_reports';

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
		self::REPORTS     => 'no',
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
	 * Returns an option as a boolean, which reads checkbox values such as 'yes' and 'no'.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string $key The option's key.
	 *
	 * @return  bool
	 */
	public static function get_bool( string $key ): bool {
		$default = self::DEFAULTS[ $key ] ?? null;
		return \filter_var( get_option( $key, $default ), \FILTER_VALIDATE_BOOL, \FILTER_NULL_ON_FAILURE ) ?? \filter_var( $default, \FILTER_VALIDATE_BOOL );
	}

	// endregion
}
