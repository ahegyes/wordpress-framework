<?php declare( strict_types=1 );
/**
 * Stays unloaded, because functions.php skips the files in includes/ whose names start with an underscore.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @package Acme\ConsumerA
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns true, so a request can tell whether this file loaded.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @return  bool
 */
function acme_ca_unloaded_include(): bool {
	return true;
}
