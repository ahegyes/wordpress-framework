<?php declare( strict_types=1 );
/**
 * Coexistence smoke — loads BOTH scoped consumers into one PHP process, the
 * multi-plugin WordPress request the per-plugin scoping design exists for.
 *
 * Failure modes this catches:
 * - an unscoped duplicate symbol (the second consumer's bootstrap function files
 *   fatal on redeclare while loading if scoping missed them).
 * - one consumer's prefix resolving into the other's scoped tree.
 * - scoped PSR-4 mappings attached to whichever vendor loader registered first
 *   instead of a dedicated loader, coupling one plugin's autoload to another
 *   plugin's lifetime.
 *
 * @package DeepWebSolutions\Framework\Tests\ConsumerSmoke
 */

// Any warning or notice during coexistence loading is a failure — e.g. a global
// define() collision between the two scoped trees warns but does not fatal.
\set_error_handler(
	static function ( int $severity, string $message, string $file, int $line ): bool {
		throw new \ErrorException( $message, 0, $severity, $file, $line );
	},
);

$fixture_a = __DIR__ . '/consumer-smoke';
$fixture_b = __DIR__ . '/consumer-smoke-b';

require $fixture_a . '/vendor/autoload.php';
require $fixture_b . '/vendor/autoload.php';

const PREFIX_A = 'DeepWebSolutions\\SmokeFixture\\Scoped\\';
const PREFIX_B = 'DeepWebSolutions\\SmokeFixtureB\\Scoped\\';

$failures = array();

// The same framework class resolves under both prefixes — two coexisting scoped copies.
$shared_class = 'DeepWebSolutions\\Framework\\Shared\\Exception\\AbstractException';
foreach ( array( PREFIX_A, PREFIX_B ) as $prefix ) {
	if ( ! class_exists( $prefix . $shared_class ) ) {
		$failures[] = "missing scoped class: $prefix$shared_class";
	}
}

// Each copy resolves from its OWN scoped tree — no cross-wiring between consumers.
foreach ( array( PREFIX_A => $fixture_a, PREFIX_B => $fixture_b ) as $prefix => $fixture_dir ) {
	if ( ! class_exists( $prefix . $shared_class ) ) {
		continue; // Already reported above.
	}
	$class_file = ( new \ReflectionClass( $prefix . $shared_class ) )->getFileName();
	if ( ! is_string( $class_file ) || ! str_starts_with( $class_file, $fixture_dir . '/dependencies/' ) ) {
		$failures[] = "$prefix$shared_class resolved from " . var_export( $class_file, true ) . " instead of $fixture_dir/dependencies/";
	}
}

// Both scoped copies of the same files-autoloaded functions coexist — framework
// (bootstrap) and third-party (PHP-DI) alike; unscoped they would collide.
$duplicated_functions = array(
	'DeepWebSolutions\\Framework\\Bootstrap\\Environment\\is_php_compatible',
	'DI\\factory',
);
foreach ( $duplicated_functions as $function ) {
	foreach ( array( PREFIX_A, PREFIX_B ) as $prefix ) {
		if ( ! function_exists( $prefix . $function ) ) {
			$failures[] = "missing scoped function: $prefix$function";
		}
	}
}

// A package only the first consumer ships still resolves with the second one loaded.
if ( ! class_exists( PREFIX_A . 'DeepWebSolutions\\Framework\\Core\\PluginKernel' ) ) {
	$failures[] = 'missing scoped class: ' . PREFIX_A . 'DeepWebSolutions\\Framework\\Core\\PluginKernel';
}

// Scoped mappings must ride each consumer's dedicated loader: a registered vendor loader
// carrying a scoped prefix means one plugin's classes live or die with another's loader.
foreach ( \Composer\Autoload\ClassLoader::getRegisteredLoaders() as $vendor_dir => $loader ) {
	foreach ( array_keys( $loader->getPrefixesPsr4() ) as $namespace ) {
		if ( str_starts_with( $namespace, PREFIX_A ) || str_starts_with( $namespace, PREFIX_B ) ) {
			$failures[] = "scoped namespace $namespace registered on the vendor loader at $vendor_dir instead of a dedicated loader";
		}
	}
}

// PSR stays global and single: one unscoped definition, no per-consumer copies.
if ( ! interface_exists( 'Psr\\Container\\ContainerInterface' ) ) {
	$failures[] = 'Psr\\Container\\ContainerInterface should resolve to the global, un-scoped definition';
}
foreach ( array( PREFIX_A, PREFIX_B ) as $prefix ) {
	if ( interface_exists( $prefix . 'Psr\\Container\\ContainerInterface' ) ) {
		$failures[] = "Psr\\Container\\ContainerInterface was incorrectly prefixed under $prefix";
	}
}

if ( array() !== $failures ) {
	fwrite( STDERR, "Coexistence smoke failed:\n" );
	foreach ( $failures as $failure ) {
		fwrite( STDERR, "  - $failure\n" );
	}
	exit( 1 );
}

echo "OK\n";
