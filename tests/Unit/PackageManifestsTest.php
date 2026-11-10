<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Unit;

use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;
use DeepWebSolutions\Framework\Tests\Unit\Support\ProvidesPackagesTrait;
use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Declare_;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class PackageManifestsTest extends TestCase {
	use ProvidesPackagesTrait;

	protected const array EXTRAS = array(
		'bootstrap'   => array(
			'branch-alias' => array( 'dev-trunk' => '2.x-dev' ),
			'requires-wp'  => '5.8',
		),
		'shared'      => array(
			'branch-alias' => array( 'dev-trunk' => '2.x-dev' ),
		),
		'core'        => array(
			'branch-alias' => array( 'dev-trunk' => '2.x-dev' ),
			'requires-wp'  => '7.1',
		),
		'settings'    => array(
			'branch-alias' => array( 'dev-trunk' => '2.x-dev' ),
			'requires-wp'  => '7.1',
		),
		'woocommerce' => array(
			'branch-alias'     => array( 'dev-trunk' => '2.x-dev' ),
			'requires-plugins' => array( 'woocommerce' ),
			'requires-wc'      => '11.2',
			'requires-wp'      => '7.1',
		),
	);

	protected const array OWNERS = array(
		'DeepWebSolutions\\Framework\\Bootstrap\\'   => 'ahegyes/wp-framework-bootstrap',
		'DeepWebSolutions\\Framework\\Shared\\'      => 'ahegyes/wp-framework-shared',
		'DeepWebSolutions\\Framework\\Core\\'        => 'ahegyes/wp-framework-core',
		'DeepWebSolutions\\Framework\\Settings\\'    => 'ahegyes/wp-framework-settings',
		'DeepWebSolutions\\Framework\\WooCommerce\\' => 'ahegyes/wp-framework-woocommerce',
		'Psr\\Clock\\'                               => 'psr/clock',
		'Psr\\Container\\'                           => 'psr/container',
		'Psr\\Log\\'                                 => 'psr/log',
		'Psr\\SimpleCache\\'                         => 'psr/simple-cache',
	);

	public function test_the_monorepo_holds_exactly_the_mapped_packages(): void {
		$directories = \array_map( 'basename', \glob( self::packages_directory() . '/*', \GLOB_ONLYDIR ) );

		self::assertEqualsCanonicalizing( \array_keys( self::REQUIRES ), $directories );
	}

	#[DataProvider( 'packages' )]
	public function test_a_package_requires_exactly_its_mapped_edges( string $package, array $requires ): void {
		$manifest = self::read_manifest( $package )['require'];
		\ksort( $manifest );

		self::assertSame( $requires, $manifest );
	}

	#[DataProvider( 'package_names' )]
	public function test_a_packages_extra_declares_exactly_its_floors_and_branch_alias( string $package ): void {
		$extra = \array_intersect_key( self::read_manifest( $package )['extra'] ?? array(), \array_flip( array( 'branch-alias', 'requires-plugins', 'requires-wc', 'requires-wp' ) ) );
		\ksort( $extra );

		self::assertSame( self::EXTRAS[ $package ], $extra );
	}

	#[DataProvider( 'package_names' )]
	public function test_a_package_references_only_its_closure( string $package ): void {
		$allowed    = self::closure( $package );
		$violations = array();

		$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( self::packages_directory() . "/$package", \FilesystemIterator::SKIP_DOTS ) );
		foreach ( new \RegexIterator( $files, '/\.php$/' ) as $file ) {
			foreach ( self::referenced_names( $file->getPathname() ) as $name ) {
				if ( self::is_foreign( $name, $allowed ) ) {
					$violations[] = $file->getPathname() . ": $name";
				}
			}
		}

		self::assertSame( array(), $violations );
	}

	public function test_a_name_a_development_package_owns_is_foreign_and_a_native_or_host_name_is_not(): void {
		$allowed = self::closure( 'core' );

		foreach ( array( TestCase::class, NodeFinder::class, 'DeepWebSolutions\\Framework\\Settings\\Forms', 'DeepWebSolutions\\Framework\\Tests\\Unit\\AutoloadTest' ) as $name ) {
			self::assertTrue( self::is_foreign( $name, $allowed ), $name );
		}

		foreach ( array( 'Psr\\Log\\LoggerInterface', \Throwable::class, 'WP_Error', 'add_action', 'ActionScheduler_Store', 'as_schedule_single_action', 'Automattic\\WooCommerce\\Utilities\\OrderUtil' ) as $name ) {
			self::assertFalse( self::is_foreign( $name, $allowed ), $name );
		}
	}

	public function test_a_closure_holds_the_package_and_every_package_below_it(): void {
		self::assertEqualsCanonicalizing(
			array( 'ahegyes/wp-framework-woocommerce', 'ahegyes/wp-framework-settings', 'ahegyes/wp-framework-core', 'ahegyes/wp-framework-bootstrap', 'ahegyes/wp-framework-shared', 'ext-filter', 'php', 'psr/clock', 'psr/container', 'psr/log', 'psr/simple-cache' ),
			self::closure( 'woocommerce' )
		);
	}

	public function test_the_bootstrap_package_is_never_autoloaded(): void {
		self::assertArrayNotHasKey( 'autoload', self::read_manifest( 'bootstrap' ) );
	}

	public static function package_namespaces(): array {
		$namespaces = array();
		foreach ( self::OWNERS as $prefix => $owner ) {
			if ( \str_starts_with( $owner, 'ahegyes/wp-framework-' ) ) {
				$package                = \substr( $owner, \strlen( 'ahegyes/wp-framework-' ) );
				$namespaces[ $package ] = array( $package, $prefix );
			}
		}

		return $namespaces;
	}

	public static function autoloaded_packages(): array {
		return \array_diff_key( self::package_namespaces(), array( 'bootstrap' => true ) );
	}

	// Composer includes a files entry on every request.
	#[DataProvider( 'autoloaded_packages' )]
	public function test_an_autoloaded_package_maps_only_its_namespace_to_src( string $package, string $prefix ): void {
		self::assertSame( array( 'psr-4' => array( $prefix => 'src/' ) ), self::read_manifest( $package )['autoload'] ?? null );
	}

	public function test_every_file_outside_the_shared_package_exits_without_wordpress_right_after_its_use_block(): void {
		$unguarded = array();
		foreach ( \array_diff( \array_keys( self::REQUIRES ), array( 'shared' ) ) as $package ) {
			$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( self::packages_directory() . "/$package", \FilesystemIterator::SKIP_DOTS ) );
			foreach ( new \RegexIterator( $files, '#(?<!/index)\.php$#' ) as $file ) {
				$code      = (string) \file_get_contents( $file->getPathname() );
				$nodes     = ( new ParserFactory() )->createForHostVersion()->parse( $code );
				$namespace = ( new NodeFinder() )->findFirstInstanceOf( $nodes, Namespace_::class );
				$statement = \current( \array_filter( $namespace?->stmts ?? $nodes, static fn ( Node $node ): bool => ! $node instanceof Declare_ && ! $node instanceof Use_ && ! $node instanceof GroupUse ) );
				if ( false === $statement || "\\defined( 'ABSPATH' ) || exit;" !== \substr( $code, $statement->getStartFilePos(), $statement->getEndFilePos() - $statement->getStartFilePos() + 1 ) ) {
					$unguarded[] = $file->getPathname();
				}
			}
		}

		self::assertSame( array(), $unguarded );
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

	// Composer drops PSR-4 violations under vendor/ without a word, so a misplaced package class would autoload in no consumer.
	#[DataProvider( 'package_namespaces' )]
	public function test_every_package_class_sits_at_the_path_its_name_implies( string $package, string $prefix ): void {
		self::assertSame( array(), self::misplaced_classes( self::packages_directory() . "/$package/src", $prefix ) );
	}

	public function test_a_class_away_from_the_path_its_name_implies_is_misplaced(): void {
		// The fixture is written at run time, because a misplaced class under tests/ would fail Composer's strict PSR-4 check.
		$directory = \sys_get_temp_dir() . '/acme-placement-' . \bin2hex( \random_bytes( 3 ) );
		\mkdir( "$directory/Wrong", 0777, true );
		\file_put_contents( "$directory/Placed.php", "<?php\nnamespace Acme;\nfinal class Placed {}\n" );
		\file_put_contents( "$directory/Wrong/Misplaced.php", "<?php\nnamespace Acme\\Right;\nfinal class Misplaced {}\n" );
		\file_put_contents( "$directory/Loose.php", "<?php\nfinal class Loose {}\n" );

		try {
			self::assertSame( array( 'Loose.php: Loose', 'Wrong/Misplaced.php: Acme\\Right\\Misplaced' ), self::misplaced_classes( $directory, 'Acme\\' ) );
		} finally {
			\unlink( "$directory/Loose.php" );
			\unlink( "$directory/Placed.php" );
			\unlink( "$directory/Wrong/Misplaced.php" );
			\rmdir( "$directory/Wrong" );
			\rmdir( $directory );
		}
	}

	public function test_a_namespace_relative_name_resolves_against_its_namespace(): void {
		$file = \dirname( __DIR__ ) . '/Fixtures/PackageManifests/relative-name.php';

		self::assertContains( 'DeepWebSolutions\\Framework\\Settings\\AdminPage\\OptionsPage', self::referenced_names( $file ) );
	}

	protected static function misplaced_classes( string $directory, string $prefix ): array {
		$misplaced = array();
		$files     = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $directory, \FilesystemIterator::SKIP_DOTS ) );
		foreach ( new \RegexIterator( $files, '/\.php$/' ) as $file ) {
			$path     = \substr( $file->getPathname(), \strlen( $directory ) + 1 );
			$expected = $prefix . \str_replace( '/', '\\', \substr( $path, 0, -4 ) );
			$nodes    = ( new ParserFactory() )->createForHostVersion()->parse( \file_get_contents( $file->getPathname() ) );
			// A file without a namespace declaration holds its classes in the global namespace.
			$namespaces = ( new NodeFinder() )->findInstanceOf( $nodes, Namespace_::class ) ?: array( new Namespace_( null, $nodes ) );
			foreach ( $namespaces as $namespace ) {
				foreach ( ( new NodeFinder() )->findInstanceOf( $namespace->stmts, ClassLike::class ) as $class ) {
					$name = \ltrim( $namespace->name?->toString() . '\\' . $class->name?->toString(), '\\' );
					if ( ! \is_null( $class->name ) && $expected !== $name ) {
						$misplaced[] = "$path: $name";
					}
				}
			}
		}

		\sort( $misplaced );

		return $misplaced;
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

	protected static function is_foreign( string $name, array $allowed ): bool {
		$owner = self::owner_of( $name );

		return ( ! \is_null( $owner ) || \str_starts_with( $name, 'Psr\\' ) || \str_starts_with( $name, 'DeepWebSolutions\\Framework\\' ) ) && ! \in_array( $owner, $allowed, true );
	}

	// WordPress, WooCommerce and Action Scheduler load nothing through Composer here, so a name the autoloader finds belongs to a development package or to the root project.
	protected static function owner_of( string $name ): ?string {
		foreach ( self::OWNERS as $prefix => $owner ) {
			if ( \str_starts_with( $name . '\\', $prefix ) ) {
				return $owner;
			}
		}

		$vendor = \dirname( __DIR__, 2 ) . '/vendor';
		$file   = ClassLoader::getRegisteredLoaders()[ $vendor ]->findFile( $name );
		if ( false === $file ) {
			return null;
		}

		$path = (string) \realpath( $file );

		return \str_starts_with( $path, "$vendor/" ) ? \implode( '/', \array_slice( \explode( '/', \substr( $path, \strlen( "$vendor/" ) ) ), 0, 2 ) ) : InstalledVersions::getRootPackage()['name'];
	}
}
