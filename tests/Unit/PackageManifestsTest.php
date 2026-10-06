<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class PackageManifestsTest extends TestCase {
	protected const array REQUIRES = array(
		'bootstrap'   => array( 'php' ),
		'shared'      => array( 'ext-filter', 'php' ),
		'core'        => array( 'ahegyes/wp-framework-shared', 'php', 'psr/container', 'psr/log' ),
		'settings'    => array( 'ahegyes/wp-framework-shared', 'php' ),
		'woocommerce' => array( 'ahegyes/wp-framework-settings', 'ahegyes/wp-framework-shared', 'php', 'psr/log' ),
	);

	// Core's root prefix comes last, because every other package's prefix extends it.
	protected const array OWNERS = array(
		'DeepWebSolutions\\Framework\\Bootstrap\\'   => 'ahegyes/wp-framework-bootstrap',
		'DeepWebSolutions\\Framework\\Shared\\'      => 'ahegyes/wp-framework-shared',
		'DeepWebSolutions\\Framework\\Settings\\'    => 'ahegyes/wp-framework-settings',
		'DeepWebSolutions\\Framework\\WooCommerce\\' => 'ahegyes/wp-framework-woocommerce',
		'DeepWebSolutions\\Framework\\'              => 'ahegyes/wp-framework-core',
		'Psr\\Container\\'                           => 'psr/container',
		'Psr\\Log\\'                                 => 'psr/log',
	);

	public static function packages(): array {
		$packages = array();
		foreach ( self::REQUIRES as $package => $requires ) {
			$packages[ $package ] = array( $package, $requires );
		}

		return $packages;
	}

	public function test_the_monorepo_holds_exactly_the_mapped_packages(): void {
		$directories = \array_map( 'basename', \glob( self::packages_directory() . '/*', \GLOB_ONLYDIR ) );

		self::assertEqualsCanonicalizing( \array_keys( self::REQUIRES ), $directories );
	}

	#[DataProvider( 'packages' )]
	public function test_a_package_requires_exactly_its_mapped_edges( string $package, array $requires ): void {
		self::assertEqualsCanonicalizing( $requires, \array_keys( self::read_manifest( $package )['require'] ) );
	}

	#[DataProvider( 'packages' )]
	public function test_a_package_references_only_itself_and_the_packages_it_requires( string $package, array $requires ): void {
		$allowed    = array( "ahegyes/wp-framework-$package", ...$requires );
		$violations = array();

		$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( self::packages_directory() . "/$package", \FilesystemIterator::SKIP_DOTS ) );
		foreach ( new \RegexIterator( $files, '/\.php$/' ) as $file ) {
			// Names built from strings at runtime are not seen.
			\preg_match_all( '/\b(?:DeepWebSolutions\\\\Framework|Psr)\\\\[\w\\\\]*/', \file_get_contents( $file->getPathname() ), $matches );
			foreach ( $matches[0] as $name ) {
				if ( ! \in_array( self::owner_of( $name ), $allowed, true ) ) {
					$violations[] = $file->getPathname() . ": $name";
				}
			}
		}

		self::assertSame( array(), $violations );
	}

	protected static function packages_directory(): string {
		return \dirname( __DIR__, 2 ) . '/packages';
	}

	protected static function read_manifest( string $package ): array {
		$path = self::packages_directory() . "/$package/composer.json";
		self::assertFileExists( $path );

		return \json_decode( \file_get_contents( $path ), true, flags: \JSON_THROW_ON_ERROR );
	}

	protected static function owner_of( string $name ): ?string {
		foreach ( self::OWNERS as $prefix => $owner ) {
			if ( \str_starts_with( $name . '\\', $prefix ) ) {
				return $owner;
			}
		}

		return null;
	}
}
