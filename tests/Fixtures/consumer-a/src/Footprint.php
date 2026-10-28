<?php declare( strict_types=1 );

namespace Acme\ConsumerA;

\defined( 'ABSPATH' ) || exit;

/**
 * Holds the data the plugin stores, which uninstall removes where the site consents.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final class Footprint {
	// region FIELDS AND CONSTANTS

	/**
	 * The plugin's stored data by kind, which covers every option, transient, meta key and scheduled job under the plugin's prefix.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     array{option_prefixes: list<non-empty-string>, meta: array{post: list<non-empty-string>}, user_meta_site_prefixes: list<non-empty-string>, user_meta_global_prefixes: list<non-empty-string>, network_options: list<non-empty-string>, cron_hooks: list<non-empty-string>, as_groups: list<non-empty-string>}
	 */
	public const array DATA = array(
		'option_prefixes'           => array( Plugin::PREFIX . '_', '_transient_' . Plugin::PREFIX . '_', '_transient_timeout_' . Plugin::PREFIX . '_' ),
		'meta'                      => array( 'post' => array( ProductData::NOTE_KEY ) ),
		'user_meta_site_prefixes'   => array( Plugin::PREFIX . '_' ),
		'user_meta_global_prefixes' => array( Plugin::PREFIX . '_' ),
		'network_options'           => array( Plugin::PREFIX . '_network_notices' ),
		'cron_hooks'                => array( Plugin::PREFIX . '_revision_run' ),
		'as_groups'                 => array( Plugin::PREFIX ),
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
}
