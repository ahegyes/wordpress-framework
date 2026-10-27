<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\ConsumerA;

use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\InstallsConsumerATrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ConsumerABuildTest extends TestCase {
	use CreatesUsersTrait;
	use InstallsConsumerATrait;

	protected const string BUILD_NOTICE = 'Acme Consumer A is missing some of its files, so its features are off. Reinstall the plugin to restore them.';

	public static function missing_build_files(): array {
		return array(
			'bootstrap-loader'  => array( 'vendor-prefixed/ahegyes/wp-framework-bootstrap/load.php', false ),
			'autoloader'        => array( 'vendor/autoload.php', true ),
			'scoped-autoloader' => array( 'vendor-prefixed/scoper-autoload.php', true ),
			'composition-root'  => array( 'src/Plugin.php', false ),
		);
	}

	#[DataProvider( 'missing_build_files' )]
	public function test_a_missing_build_file_shows_the_build_notice_to_plugin_managers_and_the_safeguard_holds( string $file, bool $warns ): void {
		$plugin = $this->install_consumer_a( 'acme-consumer-a', array( $file ) );
		$spec   = array(
			'activate' => $plugin,
			'missing'  => array( $file ),
		);

		$admin  = $this->consumer_a_request( 'GET', 'index.php', $this->make_user( 'acme_ca_admin', 'administrator' ), $spec );
		$editor = $this->consumer_a_request( 'GET', 'index.php', $this->make_user( 'acme_ca_editor', 'editor' ), $spec );

		foreach ( array( $admin, $editor ) as $request ) {
			self::assertNull( $request['died'] );
			self::assertStringContainsString( 'id="wpfooter"', $request['body'] );
			self::assertTrue( $request['records']['safeguards_loaded'] );
			self::assertFalse( $request['records']['can_export'] );
			self::assertSame( $warns, isset( $request['records']['warnings'] ) );
		}
		self::assertStringContainsString( self::BUILD_NOTICE, $admin['body'] );
		self::assertStringNotContainsString( self::BUILD_NOTICE, $editor['body'] );
	}

	public function test_a_site_below_the_php_floor_gets_core_s_message_and_the_updater_while_nothing_else_loads(): void {
		$plugin  = $this->install_consumer_a( 'acme-consumer-a', array(), array( 'Requires PHP' => '99' ) );
		$request = $this->consumer_a_request( 'GET', 'index.php', $this->make_user( 'acme_ca_admin', 'administrator' ), array( 'activate' => $plugin ) );

		self::assertNull( $request['died'] );
		self::assertStringContainsString( 'id="wpfooter"', $request['body'] );
		self::assertStringContainsString( 'does not meet minimum requirements for Acme Consumer A. The plugin requires PHP 99.', $request['body'] );
		self::assertTrue( $request['records']['updater_hooked'] );
		self::assertFalse( $request['records']['safeguards_loaded'] );
		self::assertFalse( $request['records']['functions_loaded'] );
	}
}
