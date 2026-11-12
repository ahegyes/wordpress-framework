<?php declare( strict_types=1 );

namespace Acme\ConsumerA;

\defined( 'ABSPATH' ) || exit;

/**
 * Names the plugin's stored settings and their defaults.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final class Options {
	// region FIELDS AND CONSTANTS

	/**
	 * The option that holds the plugin's settings: 'yes' under delete_data where the site consents to uninstall removing the plugin's data.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     string
	 */
	public const string SETTINGS = Plugin::PREFIX . '_settings';

	/**
	 * The settings the plugin stores on its first migration, which keep the plugin's data on uninstall.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     array{delete_data: 'yes'|'no'}
	 */
	public const array DEFAULTS = array( 'delete_data' => 'no' );

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
