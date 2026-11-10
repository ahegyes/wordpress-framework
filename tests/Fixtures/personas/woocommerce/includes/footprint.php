<?php declare( strict_types=1 );
/**
 * Declares the data the plugin stores, which uninstall removes where the site consents.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @package Acme\PersonaWooCommerce
 */

use Acme\PersonaWooCommerce\Packing;

defined( 'ABSPATH' ) || exit;

/**
 * Returns the plugin's stored data by kind: every option under the plugin's prefix, and each product's box.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @return  array{option_prefixes: list<non-empty-string>, meta: array{post: list<non-empty-string>}}
 */
function acme_pw_get_footprint(): array {
	return array(
		'option_prefixes' => array( Packing::PREFIX . '_' ),
		'meta'            => array( 'post' => array( ACME_PW_META_BOX ) ),
	);
}
