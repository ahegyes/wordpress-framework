<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Unit\Support;

trait ProvidesPackagesTrait {
	protected const array REQUIRES = array(
		'bootstrap'   => array( 'php' => '>=7.4' ),
		'shared'      => array( 'php' => '>=8.5' ),
		'core'        => array(
			'ahegyes/wp-framework-bootstrap' => '^2.0',
			'ahegyes/wp-framework-shared'    => '^2.0',
			'ext-filter'                     => '*',
			'php'                            => '>=8.5',
			'psr/clock'                      => '^1',
			'psr/container'                  => '^2.0.2',
			'psr/log'                        => '^3.0.2',
			'psr/simple-cache'               => '^3',
		),
		'settings'    => array(
			'ahegyes/wp-framework-core' => '^2.0',
			'php'                       => '>=8.5',
		),
		'woocommerce' => array(
			'ahegyes/wp-framework-settings' => '^2.0',
			'php'                           => '>=8.5',
		),
	);

	public static function packages(): array {
		$packages = array();
		foreach ( self::REQUIRES as $package => $requires ) {
			$packages[ $package ] = array( $package, $requires );
		}

		return $packages;
	}

	public static function package_names(): array {
		return \array_map( static fn ( array $package ): array => array( $package[0] ), self::packages() );
	}

	// Lists every package below the given one before it, as the packages' READMEs list their mirrors.
	protected static function closure( string $package ): array {
		$closure = array();
		foreach ( \array_keys( self::REQUIRES[ $package ] ) as $name ) {
			$closure = \array_merge( $closure, \str_starts_with( $name, 'ahegyes/wp-framework-' ) ? self::closure( \substr( $name, \strlen( 'ahegyes/wp-framework-' ) ) ) : array( $name ) );
		}

		return \array_values( \array_unique( \array_merge( $closure, array( "ahegyes/wp-framework-$package" ) ) ) );
	}
}
