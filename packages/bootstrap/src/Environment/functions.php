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
	if ( \function_exists( 'is_php_version_compatible' ) ) {
		return \is_php_version_compatible( $min_php );
	}

	return \version_compare( PHP_VERSION, $min_php, '>=' );
}

/**
 * Returns whether the running WordPress version meets the given minimum.
 * WP-native helper is unavailable on WP < 5.2; the fallback mirrors
 * `is_wp_version_compatible()` — it strips the current version's beta/alpha
 * suffix and drops a trailing `.0` from a 3-part minimum, so a 2-part current
 * like `7.0` still satisfies a `7.0.0` minimum.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string $min_wp Minimum WordPress version required.
 *
 * @return  bool
 */
function is_wp_compatible( $min_wp ) {
	if ( \function_exists( 'is_wp_version_compatible' ) ) {
		return \is_wp_version_compatible( $min_wp );
	}

	$current = isset( $GLOBALS['wp_version'] ) ? $GLOBALS['wp_version'] : '0';
	$current = \explode( '-', $current );
	$current = $current[0];

	if ( \substr_count( $min_wp, '.' ) > 1 && \substr( $min_wp, -2 ) === '.0' ) {
		$min_wp = \substr( $min_wp, 0, -2 );
	}

	return \version_compare( $current, $min_wp, '>=' );
}
