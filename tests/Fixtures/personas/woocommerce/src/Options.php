<?php declare( strict_types=1 );

namespace Acme\PersonaWooCommerce;

\defined( 'ABSPATH' ) || exit;

/**
 * Names the plugin's stored settings.
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
	public const string DELETE_DATA = 'acme_pw_delete_data';

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
}
