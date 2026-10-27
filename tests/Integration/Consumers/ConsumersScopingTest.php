<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Consumers;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ConsumersScopingTest extends TestCase {
	protected const string FIXTURES = __DIR__ . '/../../Fixtures';

	public static function closures(): array {
		return array(
			'consumer-a' => array( 'consumer-a', array( 'wp-framework-bootstrap', 'wp-framework-core', 'wp-framework-settings', 'wp-framework-shared', 'wp-framework-woocommerce' ) ),
			'consumer-b' => array( 'consumer-b', array( 'wp-framework-bootstrap', 'wp-framework-core', 'wp-framework-settings', 'wp-framework-shared' ) ),
		);
	}

	#[DataProvider( 'closures' )]
	public function test_the_scoped_build_holds_exactly_its_closure_s_framework_packages_and_psr_interfaces( string $fixture, array $packages ): void {
		$scoped = self::FIXTURES . "/$fixture/vendor-prefixed";

		self::assertSame( $packages, self::list_directories( "$scoped/ahegyes" ) );
		self::assertSame( array( 'clock', 'container', 'log', 'simple-cache' ), self::list_directories( "$scoped/psr" ) );
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

	protected static function list_directories( string $path ): array {
		self::assertDirectoryExists( $path );
		$directories = \array_map( 'basename', (array) \glob( "$path/*", \GLOB_ONLYDIR ) );
		\sort( $directories );

		return $directories;
	}
}
