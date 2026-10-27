<?php declare( strict_types=1 );
/**
 * Removes the plugin's data where the site consents, through the framework's uninstall engine, once the site meets the plugin's floors.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @package Acme\ConsumerA
 */

use Acme\ConsumerA\Plugin;
use Acme\ConsumerA\Scoped\DeepWebSolutions\Framework\Bootstrap\Requirements;
use Acme\ConsumerA\Scoped\DeepWebSolutions\Framework\Core\Uninstall\Uninstaller;
use Acme\ConsumerA\Scoped\DeepWebSolutions\Framework\WooCommerce\Uninstall\MetaTypes;

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$acme_ca_file = __DIR__ . '/acme-consumer-a.php';
try {
	$acme_ca_bootstrap = __DIR__ . '/vendor-prefixed/ahegyes/wp-framework-bootstrap/load.php';
	if ( ! is_readable( $acme_ca_bootstrap ) ) {
		throw new RuntimeException( 'its bootstrap package is missing.' );
	}

	require_once $acme_ca_bootstrap;
	if ( ! Requirements::check_floors( $acme_ca_file ) ) {
		throw new RuntimeException( 'the site is below its PHP or WordPress floor.' );
	}

	require_once __DIR__ . '/vendor/autoload.php';
} catch ( Throwable $error ) {
	// WP-CLI then fails and keeps the files for a rerun on a complete build, while a web request deletes them and leaves the data.
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		throw $error;
	}

	error_log( 'Acme Consumer A kept its data on uninstall, because ' . $error->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- The uninstall request has no other channel.
	return;
}

Uninstaller::run(
	$acme_ca_file,
	Plugin::FOOTPRINT,
	array(
		'consent'    => array(
			'option' => Plugin::PREFIX . '_settings',
			'key'    => 'delete_data',
		),
		'meta_types' => MetaTypes::DEFINITIONS,
	)
);
