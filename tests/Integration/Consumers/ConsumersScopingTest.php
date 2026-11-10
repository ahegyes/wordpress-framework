<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Consumers;

use DeepWebSolutions\Framework\Tests\Support\CopiesFixturesTrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ConsumersScopingTest extends TestCase {
	use CopiesFixturesTrait;

	public static function builds(): array {
		$psr = array( 'clock', 'container', 'log', 'simple-cache' );

		return array(
			'consumer-a'          => array( 'consumer-a', null, array( 'wp-framework-bootstrap', 'wp-framework-core', 'wp-framework-settings', 'wp-framework-shared', 'wp-framework-woocommerce' ), $psr ),
			'consumer-b'          => array( 'consumer-b', null, array( 'wp-framework-bootstrap', 'wp-framework-core', 'wp-framework-settings', 'wp-framework-shared' ), $psr ),
			'persona-bootstrap'   => array( 'personas/bootstrap', array( 'ahegyes', 'scoper-autoload.php' ), array( 'wp-framework-bootstrap' ), array() ),
			'persona-shared'      => array( 'personas/shared', array( 'ahegyes', 'scoper-autoload.php' ), array( 'wp-framework-shared' ), array() ),
			'persona-core'        => array( 'personas/core', null, array( 'wp-framework-bootstrap', 'wp-framework-core', 'wp-framework-shared' ), $psr ),
			'persona-settings'    => array( 'personas/settings', array( 'ahegyes', 'psr', 'scoper-autoload.php' ), array( 'wp-framework-bootstrap', 'wp-framework-core', 'wp-framework-settings', 'wp-framework-shared' ), $psr ),
			'persona-woocommerce' => array( 'personas/woocommerce', array( 'ahegyes', 'psr', 'scoper-autoload.php' ), array( 'wp-framework-bootstrap', 'wp-framework-core', 'wp-framework-settings', 'wp-framework-shared', 'wp-framework-woocommerce' ), $psr ),
		);
	}

	// PHP-DI's own dependencies change between releases, so the top level of a build that ships it stays unlisted.
	#[DataProvider( 'builds' )]
	public function test_the_production_build_ships_its_closure_scoped_and_nothing_unscoped( string $fixture, ?array $top_level, array $packages, array $psr ): void {
		$build = self::FIXTURES . "/$fixture";
		self::assertFileExists( "$build/vendor/composer/installed.json", "Build $fixture first with `composer fixtures:build`." );

		$installed = \json_decode( (string) \file_get_contents( "$build/vendor/composer/installed.json" ), true );
		self::assertIsArray( $installed );
		self::assertFalse( $installed['dev'] );
		self::assertSame( array(), $installed['packages'] );
		if ( ! \is_null( $top_level ) ) {
			self::assertSame( $top_level, self::list_entries( "$build/vendor-prefixed" ) );
		}

		self::assertSame( $packages, self::list_entries( "$build/vendor-prefixed/ahegyes" ) );
		self::assertSame( $psr, self::list_entries( "$build/vendor-prefixed/psr" ) );
		self::assertSame( \in_array( 'wp-framework-bootstrap', $packages, true ), \is_file( "$build/vendor-prefixed/ahegyes/wp-framework-bootstrap/load.php" ) );
	}

	#[RunInSeparateProcess]
	public function test_both_builds_autoload_their_own_prefixed_copies_side_by_side(): void {
		require_once self::FIXTURES . '/consumer-a/vendor/autoload.php';
		require_once self::FIXTURES . '/consumer-b/vendor/autoload.php';

		$builds = array(
			'Acme\\ConsumerA\\Scoped\\' => array( 'Shared\\Result\\Success', 'Core\\PluginKernel', 'Settings\\Forms', 'WooCommerce\\WooCommerceRequirement' ),
			'Acme\\ConsumerB\\Scoped\\' => array( 'Shared\\Result\\Success', 'Core\\PluginKernel', 'Settings\\Forms' ),
		);
		foreach ( $builds as $prefix => $classes ) {
			self::assertTrue( \interface_exists( $prefix . 'Psr\\Log\\LoggerInterface' ) );
			foreach ( $classes as $class ) {
				self::assertTrue( \class_exists( $prefix . 'DeepWebSolutions\\Framework\\' . $class ) );
			}
		}

		$logger = 'DeepWebSolutions\\Framework\\Core\\Logging\\ErrorLogLogger';
		self::assertTrue( \is_subclass_of( 'Acme\\ConsumerA\\Scoped\\' . $logger, 'Acme\\ConsumerA\\Scoped\\Psr\\Log\\LoggerInterface' ) );
		self::assertFalse( \is_subclass_of( 'Acme\\ConsumerA\\Scoped\\' . $logger, 'Acme\\ConsumerB\\Scoped\\Psr\\Log\\LoggerInterface' ) );
		self::assertTrue( \is_subclass_of( 'Acme\\ConsumerB\\Scoped\\' . $logger, 'Acme\\ConsumerB\\Scoped\\Psr\\Log\\LoggerInterface' ) );
		self::assertFalse( \is_subclass_of( 'Acme\\ConsumerB\\Scoped\\' . $logger, 'Acme\\ConsumerA\\Scoped\\Psr\\Log\\LoggerInterface' ) );
	}

	#[RunInSeparateProcess]
	public function test_the_scoped_woocommerce_settings_classes_extend_woocommerce_s_own(): void {
		require_once WC_ABSPATH . 'includes/admin/settings/class-wc-settings-page.php';
		require_once self::FIXTURES . '/consumer-a/vendor/autoload.php';

		$scoped = 'Acme\\ConsumerA\\Scoped\\DeepWebSolutions\\Framework\\WooCommerce\\Setting\\';
		self::assertSame( 'Automattic\\WooCommerce\\Admin\\Settings\\SettingsSection', \get_parent_class( $scoped . 'SettingsSection' ) );
		self::assertSame( 'WC_Settings_Page', \get_parent_class( $scoped . 'SettingsTab' ) );
	}

	protected static function list_entries( string $path ): array {
		$entries = \is_dir( $path ) ? \array_values( \array_diff( (array) \scandir( $path ), array( '.', '..' ) ) ) : array();
		\sort( $entries );

		return $entries;
	}
}
