<?php declare( strict_types=1 );
/**
 * Holds the plugin's option and meta keys that more than one file reads.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @package Acme\PersonaWooCommerce
 */

use Acme\PersonaWooCommerce\Packing;

defined( 'ABSPATH' ) || exit;

/**
 * The option that holds 'yes' where the site consents to uninstall removing the plugin's data.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @var     string
 */
const ACME_PW_OPTION_DELETE_DATA = Packing::PREFIX . '_delete_data';

/**
 * The meta key of each product's box.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @var     string
 */
const ACME_PW_META_BOX = '_' . Packing::PREFIX . '_box';
