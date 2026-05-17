<?php

namespace DeepWebSolutions\Framework\Bootstrap\Environment;

/**
 * Returns whether the running PHP version meets the given minimum. WP-native
 * helper is unavailable on WP < 5.2.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string $min_php Minimum PHP version required.
 *
 * @return  bool
 */
function is_php_compatible( $min_php ) {
	if ( \function_exists( '\is_php_version_compatible' ) ) {
		return \is_php_version_compatible( $min_php );
	}

	return \version_compare( PHP_VERSION, $min_php, '>=' );
}

/**
 * Returns whether the running WordPress version meets the given minimum.
 * WP-native helper is unavailable on WP < 5.2; the fallback strips the
 * beta/alpha suffix the way `wp_get_wp_version()` does.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string $min_wp Minimum WordPress version required.
 *
 * @return  bool
 */
function is_wp_compatible( $min_wp ) {
	if ( \function_exists( '\is_wp_version_compatible' ) ) {
		return \is_wp_version_compatible( $min_wp );
	}

	$current = isset( $GLOBALS['wp_version'] ) ? $GLOBALS['wp_version'] : '0';
	$parts   = \explode( '-', $current );

	return \version_compare( $parts[0], $min_wp, '>=' );
}
