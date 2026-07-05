<?php declare( strict_types=1 );
/**
 * Runs composer-require-checker against every framework package.
 *
 * The packages install only through the monorepo root vendor (they are wired as
 * path repositories there), so a standalone per-package `composer install` is
 * unavailable and the checker cannot resolve a package's cross-package or
 * Composer dependencies on its own. For each package this script points the
 * package vendor at the root vendor for the duration of the check, derives the
 * WordPress / WooCommerce stub files to treat as host symbols from the package's
 * own `extra.scoping-stubs`, and layers only the matching host-symbol allow-lists
 * on top. It exits non-zero when a package uses a Composer symbol it does not
 * declare in `require`.
 */

$root = \dirname( __DIR__ );

$packages = array( 'bootstrap', 'shared', 'core', 'infrastructure', 'woocommerce' );

$checker = $root . '/vendor/bin/composer-require-checker';
if ( ! \is_file( $checker ) ) {
	\fwrite( \STDERR, "composer-require-checker is not installed; run `composer install` first.\n" );
	exit( 2 );
}

$read_config = static function ( string $path ): array {
	$config = \json_decode( (string) \file_get_contents( $path ), true );
	if ( ! \is_array( $config ) ) {
		\fwrite( \STDERR, \basename( $path ) . " is missing or not valid JSON.\n" );
		exit( 2 );
	}

	return $config;
};

$read_symbol_whitelist = static function ( string $path ) use ( $read_config ): array {
	$config = $read_config( $path );
	$symbols = $config['symbol-whitelist'] ?? array();
	if ( ! \is_array( $symbols ) ) {
		\fwrite( \STDERR, \basename( $path ) . " must define symbol-whitelist as an array.\n" );
		exit( 2 );
	}

	foreach ( $symbols as $symbol ) {
		if ( ! \is_string( $symbol ) ) {
			\fwrite( \STDERR, \basename( $path ) . " contains a non-string symbol-whitelist entry.\n" );
			exit( 2 );
		}
	}

	return $symbols;
};

$base = $read_config( $root . '/composer-require-checker.json' );
$shared_symbol_whitelist = $read_symbol_whitelist( $root . '/composer-require-checker.json' );

$surface_allow_lists = array(
	'php-stubs/woocommerce-stubs' => $root . '/composer-require-checker.woocommerce.json',
);

$cache_dir = $root . '/tests/.cache/require-checker';
if ( ! \is_dir( $cache_dir ) ) {
	\mkdir( $cache_dir, 0777, true );
}

// The vendor symlinks below live inside the source tree, so remove them even when a check aborts.
$links = array();
\register_shutdown_function(
	static function () use ( &$links ): void {
		foreach ( $links as $link ) {
			if ( \is_link( $link ) ) {
				\unlink( $link );
			}
		}
	}
);

$failed = array();

foreach ( $packages as $package ) {
	$package_dir = $root . '/packages/' . $package;
	$manifest    = \json_decode( (string) \file_get_contents( $package_dir . '/composer.json' ), true );

	// The stub files the package already declares for scoping are exactly the
	// host symbols (WordPress, WooCommerce, Action Scheduler) it is allowed to use.
	$scan_files = array();
	$symbol_whitelist = $shared_symbol_whitelist;
	foreach ( $manifest['extra']['scoping-stubs'] ?? array() as $stub ) {
		$segments     = \explode( ':', $stub, 2 );
		$stub_file    = $segments[1] ?? \basename( $segments[0] ) . '.php';
		$scan_files[] = 'vendor/' . $segments[0] . '/' . $stub_file;

		if ( isset( $surface_allow_lists[ $segments[0] ] ) ) {
			$symbol_whitelist = \array_merge(
				$symbol_whitelist,
				$read_symbol_whitelist( $surface_allow_lists[ $segments[0] ] ),
			);
		}
	}

	$config                     = $base;
	$config['scan-files']       = $scan_files;
	$config['symbol-whitelist'] = \array_values( \array_unique( $symbol_whitelist ) );
	$config_path                = $cache_dir . '/' . $package . '.json';
	\file_put_contents( $config_path, \json_encode( $config, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES ) );

	$link = $package_dir . '/vendor';
	if ( \is_link( $link ) ) {
		// A leftover symlink from an interrupted run is ours to replace; a symlink pointing elsewhere is not.
		if ( '../../vendor' !== \readlink( $link ) ) {
			\fwrite( \STDERR, "Unexpected vendor symlink at $link; aborting to avoid clobbering it.\n" );
			exit( 2 );
		}
		\unlink( $link );
	} elseif ( \file_exists( $link ) ) {
		\fwrite( \STDERR, "Unexpected real vendor directory at $link; aborting to avoid clobbering it.\n" );
		exit( 2 );
	}
	if ( ! \symlink( '../../vendor', $link ) ) {
		\fwrite( \STDERR, "Failed to create the temporary vendor symlink at $link.\n" );
		exit( 2 );
	}
	$links[] = $link;

	// The WordPress + WooCommerce stubs are multi-megabyte; raise the limit so the parser does not exhaust memory.
	$command = \sprintf(
		'%s -d memory_limit=1G %s check %s --config-file=%s 2>&1',
		\escapeshellarg( \PHP_BINARY ),
		\escapeshellarg( $checker ),
		\escapeshellarg( $package_dir . '/composer.json' ),
		\escapeshellarg( $config_path )
	);

	$output = array();
	$status = 0;
	\exec( $command, $output, $status );

	\unlink( $link );

	echo "── $package ──\n";
	echo \implode( "\n", $output ) . "\n\n";

	if ( 0 !== $status ) {
		$failed[] = $package;
	}
}

if ( array() !== $failed ) {
	\fwrite( \STDERR, 'Undeclared Composer dependencies found in: ' . \implode( ', ', $failed ) . "\n" );
	exit( 1 );
}

echo "composer-require-checker: every package declares the Composer symbols it uses.\n";
exit( 0 );
