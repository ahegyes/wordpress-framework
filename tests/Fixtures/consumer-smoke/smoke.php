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

// Namespaced multi-segment prefix, matching the plugin-template convention — php-scoper
// writes it with doubled backslashes inside string literals, the path flat prefixes hide.
const SCOPED_PREFIX = 'DeepWebSolutions\\SmokeFixture\\Scoped\\';

$failures = array();

// Bootstrap files-autoload: the package's nested function files must be loaded.
$bootstrap_functions = array(
	SCOPED_PREFIX . 'DeepWebSolutions\\Framework\\Bootstrap\\Environment\\is_php_compatible',
	SCOPED_PREFIX . 'DeepWebSolutions\\Framework\\Bootstrap\\Requirements\\check_requirements',
);
foreach ( $bootstrap_functions as $function ) {
	if ( ! function_exists( $function ) ) {
		$failures[] = "missing scoped function: $function";
	}
}

// Core PSR-4: PluginKernel + interfaces + boot-report value objects.
$core_classes = array(
	SCOPED_PREFIX . 'DeepWebSolutions\\Framework\\Core\\PluginKernel',
	SCOPED_PREFIX . 'DeepWebSolutions\\Framework\\Core\\PluginInterface',
	SCOPED_PREFIX . 'DeepWebSolutions\\Framework\\Core\\ValueObjects\\BootStatus',
	SCOPED_PREFIX . 'DeepWebSolutions\\Framework\\Core\\ValueObjects\\PluginBootReport',
);
foreach ( $core_classes as $class ) {
	if ( ! class_exists( $class ) && ! interface_exists( $class ) ) {
		$failures[] = "missing scoped class/interface: $class";
	}
}

// Storage PSR-4: the extracted leaf package's classes resolve under the scoped prefix.
if ( ! class_exists( SCOPED_PREFIX . 'DeepWebSolutions\\Framework\\Storage\\MemoryStore' ) ) {
	$failures[] = 'missing scoped class: DeepWebSolutions\\Framework\\Storage\\MemoryStore';
}

// Utilities PSR-4: the rollup package's classes resolve under the scoped prefix, with the
// unscoped Psr\Log contract CompositeLogger implements provided by the consumer's vendor.
if ( ! class_exists( SCOPED_PREFIX . 'DeepWebSolutions\\Framework\\Utilities\\Logging\\CompositeLogger' ) ) {
	$failures[] = 'missing scoped class: DeepWebSolutions\\Framework\\Utilities\\Logging\\CompositeLogger';
}

// Settings PSR-4: the descriptor value objects resolve under the scoped prefix.
if ( ! class_exists( SCOPED_PREFIX . 'DeepWebSolutions\\Framework\\Settings\\Schema\\ValueObjects\\SettingsField' ) ) {
	$failures[] = 'missing scoped class: DeepWebSolutions\\Framework\\Settings\\Schema\\ValueObjects\\SettingsField';
}

// WooCommerce order-field store: the class that references WooCommerce symbols resolves under the
// scoped prefix, with those symbols left unprefixed via the fixture's woocommerce-stubs catalog.
if ( ! class_exists( SCOPED_PREFIX . 'DeepWebSolutions\\Framework\\WooCommerce\\OrderData\\OrderFieldStore' ) ) {
	$failures[] = 'missing scoped class: DeepWebSolutions\\Framework\\WooCommerce\\OrderData\\OrderFieldStore';
}

// PHP-DI PSR-4 + files-autoloaded factory().
if ( ! class_exists( SCOPED_PREFIX . 'DI\\ContainerBuilder' ) ) {
	$failures[] = 'missing scoped class: DI\\ContainerBuilder';
}
if ( ! function_exists( SCOPED_PREFIX . 'DI\\factory' ) ) {
	$failures[] = 'missing scoped function: DI\\factory';
}

// PSR\Container must NOT be prefixed — the global namespace stays as-is.
if ( ! interface_exists( 'Psr\\Container\\ContainerInterface' ) ) {
	$failures[] = 'Psr\\Container\\ContainerInterface should resolve to the global, un-scoped definition';
}
if ( interface_exists( SCOPED_PREFIX . 'Psr\\Container\\ContainerInterface' ) ) {
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
