<?php
/**
 * PHPUnit bootstrap for the framework monorepo.
 *
 * Loads Composer's autoload (which triggers the `files`-autoloaded
 * `check-requirements.php`). When running inside wp-env's `tests-cli`
 * container, also loads WordPress so integration tests can exercise real
 * WP_Error / get_plugin_data / add_action / wp_admin_notice. Unit tests run
 * locally with composer autoload alone — no WP — so wrapper "outside WP"
 * fallback paths are exercised (e.g., `is_php_compatible` returns false).
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Inside wp-env's tests-cli container, WordPress lives at /var/www/html.
// Load it so integration tests have real WP available. Outside the container
// (local unit-test runs), this file doesn't exist — bootstrap stops here.
$wp_load = '/var/www/html/wp-load.php';
if ( file_exists( $wp_load ) ) {
	require_once $wp_load;
}
