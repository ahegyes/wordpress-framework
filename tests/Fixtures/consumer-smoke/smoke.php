<?php declare( strict_types=1 );
/**
 * Smoke run — exercises every framework symbol the consumer contract exposes,
 * forcing autoload to resolve scoped classes/functions across all three sources
 * (bootstrap files-autoload, core/shared/storage/utilities/woocommerce PSR-4,
 * PHP-DI PSR-4 + transitive deps).
 *
 * Failure modes this catches:
 * - autoload.files entries from each scoped package not loaded by the generator
 *   (bootstrap, shared, core, php-di functions).
 * - PSR-4 mappings for scoped framework + PHP-DI packages missing or mis-pathed.
 * - PHP-DI's Template.php scoped despite being in exclude_files.
 * - PSR (e.g. ContainerInterface) prefixed when it shouldn't be.
 *
 * @package DeepWebSolutions\Framework\Tests\ConsumerSmoke
 */

require __DIR__ . '/vendor/autoload.php';

$failures = array();

// Bootstrap files-autoload: the package's nested function files must be loaded.
$bootstrap_functions = array(
	'DWS_CONSUMER_SMOKE_Deps\\DeepWebSolutions\\Framework\\Bootstrap\\Environment\\is_php_compatible',
	'DWS_CONSUMER_SMOKE_Deps\\DeepWebSolutions\\Framework\\Bootstrap\\Requirements\\check_requirements',
);
foreach ( $bootstrap_functions as $function ) {
	if ( ! function_exists( $function ) ) {
		$failures[] = "missing scoped function: $function";
	}
}

// Core PSR-4: PluginKernel + interfaces.
$core_classes = array(
	'DWS_CONSUMER_SMOKE_Deps\\DeepWebSolutions\\Framework\\Core\\PluginKernel',
	'DWS_CONSUMER_SMOKE_Deps\\DeepWebSolutions\\Framework\\Core\\PluginInterface',
);
foreach ( $core_classes as $class ) {
	if ( ! class_exists( $class ) && ! interface_exists( $class ) ) {
		$failures[] = "missing scoped class/interface: $class";
	}
}

// Storage PSR-4: the extracted leaf package's classes resolve under the scoped prefix.
if ( ! class_exists( 'DWS_CONSUMER_SMOKE_Deps\\DeepWebSolutions\\Framework\\Storage\\MemoryStore' ) ) {
	$failures[] = 'missing scoped class: DeepWebSolutions\\Framework\\Storage\\MemoryStore';
}

// Settings PSR-4: the descriptor value objects resolve under the scoped prefix.
if ( ! class_exists( 'DWS_CONSUMER_SMOKE_Deps\\DeepWebSolutions\\Framework\\Settings\\ValueObjects\\SettingsField' ) ) {
	$failures[] = 'missing scoped class: DeepWebSolutions\\Framework\\Settings\\ValueObjects\\SettingsField';
}

// Settings WooCommerce-coupled backend: the class that references WooCommerce symbols resolves under
// the scoped prefix, with those symbols left unprefixed via the fixture's woocommerce-stubs catalog.
if ( ! class_exists( 'DWS_CONSUMER_SMOKE_Deps\\DeepWebSolutions\\Framework\\Settings\\WordPressObjectFieldStore' ) ) {
	$failures[] = 'missing scoped class: DeepWebSolutions\\Framework\\Settings\\WordPressObjectFieldStore';
}

// PHP-DI PSR-4 + files-autoloaded factory().
if ( ! class_exists( 'DWS_CONSUMER_SMOKE_Deps\\DI\\ContainerBuilder' ) ) {
	$failures[] = 'missing scoped class: DI\\ContainerBuilder';
}
if ( ! function_exists( 'DWS_CONSUMER_SMOKE_Deps\\DI\\factory' ) ) {
	$failures[] = 'missing scoped function: DI\\factory';
}

// PSR\Container must NOT be prefixed — the global namespace stays as-is.
if ( ! interface_exists( 'Psr\\Container\\ContainerInterface' ) ) {
	$failures[] = 'Psr\\Container\\ContainerInterface should resolve to the global, un-scoped definition';
}
if ( interface_exists( 'DWS_CONSUMER_SMOKE_Deps\\Psr\\Container\\ContainerInterface' ) ) {
	$failures[] = 'Psr\\Container\\ContainerInterface was incorrectly prefixed';
}

if ( array() !== $failures ) {
	fwrite( STDERR, "Smoke failed:\n" );
	foreach ( $failures as $failure ) {
		fwrite( STDERR, "  - $failure\n" );
	}
	exit( 1 );
}

echo "OK\n";
