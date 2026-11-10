<?php declare( strict_types=1 );
/**
 * Removes the plugin's data where the site consents, through the framework's uninstall engine, once the site meets the plugin's floors.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @package Acme\PersonaCore
 */

use Acme\PersonaCore\Scoped\DeepWebSolutions\Framework\Bootstrap\Requirements;
use Acme\PersonaCore\Scoped\DeepWebSolutions\Framework\Core\Uninstall\Uninstaller;

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$acme_pc_file = __DIR__ . '/acme-persona-core.php';
try {
	$acme_pc_bootstrap = __DIR__ . '/vendor-prefixed/ahegyes/wp-framework-bootstrap/load.php';
	if ( ! is_readable( $acme_pc_bootstrap ) ) {
		throw new RuntimeException( 'its bootstrap package is missing.' );
	}

	require_once $acme_pc_bootstrap;
	if ( ! Requirements::check_floors( $acme_pc_file ) ) {
		throw new RuntimeException( 'the site is below its PHP or WordPress floor.' );
	}

	require_once __DIR__ . '/vendor/autoload.php';
	require_once __DIR__ . '/includes/footprint.php';
	require_once __DIR__ . '/includes/options.php';
	$acme_pc_footprint = acme_pc_get_footprint();
	$acme_pc_consent   = ACME_PC_OPTION_SETTINGS;
	if ( ! class_exists( Uninstaller::class ) ) {
		throw new RuntimeException( 'its uninstall engine is missing.' );
	}
} catch ( Throwable $error ) {
	// WP-CLI then fails and keeps the files for a rerun on a complete build, while a web request deletes them and leaves the data.
	if ( defined( 'WP_CLI' ) && true === WP_CLI ) {
		throw $error;
	}

	error_log( 'Acme Persona Core kept its data on uninstall, because ' . $error->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- The uninstall request has no other channel.
	return;
}

Uninstaller::run(
	$acme_pc_file,
	$acme_pc_footprint,
	array(
		'consent' => array(
			'option' => $acme_pc_consent,
			'key'    => 'delete_data',
		),
	)
);
