<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Personas;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class PersonasScopingTest extends TestCase {
	protected const string PERSONAS = __DIR__ . '/../../Fixtures/personas';

	public static function trees(): array {
		$psr = array( 'clock', 'container', 'log', 'simple-cache' );

		return array(
			'bootstrap'   => array( 'bootstrap', array( 'ahegyes', 'scoper-autoload.php' ), array( 'wp-framework-bootstrap' ), array() ),
			'shared'      => array( 'shared', array( 'ahegyes', 'scoper-autoload.php' ), array( 'wp-framework-shared' ), array() ),
			'core'        => array( 'core', null, array( 'wp-framework-bootstrap', 'wp-framework-core', 'wp-framework-shared' ), $psr ),
			'settings'    => array( 'settings', array( 'ahegyes', 'psr', 'scoper-autoload.php' ), array( 'wp-framework-bootstrap', 'wp-framework-core', 'wp-framework-settings', 'wp-framework-shared' ), $psr ),
			'woocommerce' => array( 'woocommerce', array( 'ahegyes', 'psr', 'scoper-autoload.php' ), array( 'wp-framework-bootstrap', 'wp-framework-core', 'wp-framework-settings', 'wp-framework-shared', 'wp-framework-woocommerce' ), $psr ),
		);
	}

	// PHP-DI's own dependencies change between releases, so the core persona's top level stays unlisted.
	#[DataProvider( 'trees' )]
	public function test_the_production_build_ships_its_closure_scoped_and_nothing_unscoped( string $persona, ?array $top_level, array $packages, array $psr ): void {
		$build = self::PERSONAS . "/$persona";
		self::assertFileExists( "$build/vendor/composer/installed.json", "Build personas/$persona first with `composer fixtures:build`." );

		$installed = \json_decode( (string) \file_get_contents( "$build/vendor/composer/installed.json" ), true );
		self::assertIsArray( $installed );
		self::assertFalse( $installed['dev'] );
		self::assertSame( array(), $installed['packages'] );
		if ( null !== $top_level ) {
			self::assertSame( $top_level, self::list_entries( "$build/vendor-prefixed" ) );
		}
		self::assertSame( $packages, self::list_entries( "$build/vendor-prefixed/ahegyes" ) );
		self::assertSame( $psr, self::list_entries( "$build/vendor-prefixed/psr" ) );
	}

	protected static function list_entries( string $path ): array {
		$entries = \is_dir( $path ) ? \array_values( \array_diff( (array) \scandir( $path ), array( '.', '..' ) ) ) : array();
		\sort( $entries );

		return $entries;
	}
}
