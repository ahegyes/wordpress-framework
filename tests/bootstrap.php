<?php declare( strict_types=1 );

require_once __DIR__ . '/../vendor/autoload.php';

// Inside wp-env's cli container WordPress lives here. The guard lets unit
// tests run locally without WP loaded.
$wp_load = '/var/www/html/wp-load.php';
if ( file_exists( $wp_load ) ) {
	require_once $wp_load;
}
