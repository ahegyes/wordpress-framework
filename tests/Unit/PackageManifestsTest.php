<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Unit;

use PhpParser\Node\Name;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class PackageManifestsTest extends TestCase {
	protected const array REQUIRES = array(
		'bootstrap'   => array( 'php' ),
		'shared'      => array( 'php' ),
		'core'        => array( 'ahegyes/wp-framework-shared', 'ext-filter', 'php', 'psr/clock', 'psr/container', 'psr/log' ),
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
		'Psr\\Clock\\'                               => 'psr/clock',
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
			foreach ( self::referenced_names( $file->getPathname() ) as $name ) {
				$owner = self::owner_of( $name );
				if ( ( ! \is_null( $owner ) || \str_starts_with( $name, 'Psr\\' ) ) && ! \in_array( $owner, $allowed, true ) ) {
					$violations[] = $file->getPathname() . ": $name";
				}
			}
		}

		self::assertSame( array(), $violations );
	}

	public function test_the_bootstrap_package_is_never_autoloaded(): void {
		self::assertArrayNotHasKey( 'autoload', self::read_manifest( 'bootstrap' ) );
	}

	public static function autoloaded_packages(): array {
		return array(
			'shared'      => array( 'shared', 'DeepWebSolutions\\Framework\\Shared\\' ),
			'core'        => array( 'core', 'DeepWebSolutions\\Framework\\' ),
			'settings'    => array( 'settings', 'DeepWebSolutions\\Framework\\Settings\\' ),
			'woocommerce' => array( 'woocommerce', 'DeepWebSolutions\\Framework\\WooCommerce\\' ),
		);
	}

	// Composer includes a files entry on every request, and anything outside src/ is loaded by path on runtimes below the floor.
	#[DataProvider( 'autoloaded_packages' )]
	public function test_an_autoloaded_package_maps_only_its_namespace_to_src( string $package, string $prefix ): void {
		self::assertSame( array( 'psr-4' => array( $prefix => 'src/' ) ), self::read_manifest( $package )['autoload'] ?? null );
	}

	public function test_every_package_directory_carries_an_index_stub(): void {
		$missing = array();
		foreach ( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( self::packages_directory(), \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::SELF_FIRST ) as $file ) {
			if ( $file->isDir() && ! \is_file( $file->getPathname() . '/index.php' ) ) {
				$missing[] = $file->getPathname();
			}
		}

		self::assertSame( array(), $missing );
	}

	public function test_a_namespace_relative_name_resolves_against_its_namespace(): void {
		$file = \dirname( __DIR__ ) . '/Fixtures/package-manifests/relative-name.php';

		self::assertContains( 'DeepWebSolutions\\Framework\\Settings\\OptionsPage', self::referenced_names( $file ) );
	}

	protected static function packages_directory(): string {
		return \dirname( __DIR__, 2 ) . '/packages';
	}

	protected static function read_manifest( string $package ): array {
		$path = self::packages_directory() . "/$package/composer.json";
		self::assertFileExists( $path );

		return \json_decode( \file_get_contents( $path ), true, flags: \JSON_THROW_ON_ERROR );
	}

	protected static function referenced_names( string $file ): array {
		// Names built from strings at runtime and types named only in docblocks are not seen.
		$nodes = ( new NodeTraverser( new NameResolver() ) )->traverse( ( new ParserFactory() )->createForHostVersion()->parse( \file_get_contents( $file ) ) );

		return \array_map( static fn ( Name $name ): string => $name->toString(), ( new NodeFinder() )->findInstanceOf( $nodes, Name::class ) );
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
