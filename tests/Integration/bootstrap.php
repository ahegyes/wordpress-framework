<?php declare( strict_types=1 );

// WordPress ends the process with exit code 0 when it cannot load, which would otherwise pass the suite.
$wp_framework_loaded = false;
register_shutdown_function(
	static function () use ( &$wp_framework_loaded ): void {
		if ( ! $wp_framework_loaded ) {
			fwrite( STDERR, "WordPress exited while the Integration bootstrap was loading it.\n" );
			exit( 1 );
		}
	}
);

// wp-env maps the repository to wp-content/mu-plugins/wp-framework, three levels below the WordPress root.
require_once dirname( __DIR__, 5 ) . '/wp-load.php';
// Admin pages load the plugin API before any admin notice renders.
require_once ABSPATH . 'wp-admin/includes/plugin.php';
// A plugin's entry file loads the bootstrap package, which core and woocommerce print their boot notices through.
require_once dirname( __DIR__, 2 ) . '/packages/bootstrap/load.php';

$wp_framework_loaded = true;

// The admin request processes share this opcache file cache, which each run starts empty, so no compiled script outlives the run.
define( 'WP_FRAMEWORK_OPCACHE', sys_get_temp_dir() . '/wp-framework-opcache' );
exec( 'rm -rf ' . escapeshellarg( WP_FRAMEWORK_OPCACHE ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Empties the previous run's cache.
mkdir( WP_FRAMEWORK_OPCACHE ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Opcache needs its cache directory to exist.
