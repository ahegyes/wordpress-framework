<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\ConsumerB;

use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\InstallsConsumersTrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Group( 'without-woocommerce' )]
final class ConsumerBBootTest extends TestCase {
	use CreatesUsersTrait;
	use InstallsConsumersTrait;

	public function test_the_plugin_boots_without_woocommerce_and_its_settings_tab_renders_its_field(): void {
		$plugin  = $this->install_consumer( 'consumer-b', 'acme-consumer-b' );
		$request = $this->consumer_request( 'GET', 'options-general.php?page=acme_cb', $this->make_user( 'acme_cb_admin', 'administrator' ), array( 'activate' => $plugin ) );

		self::assertNull( $request['died'] );
		self::assertFalse( $request['records']['woocommerce_present'] );
		self::assertSame( 'booted', $request['records']['consumer_b']['status'] );
		self::assertSame( array( 'Acme\\ConsumerB\\Scoped\\DeepWebSolutions\\Framework\\Core\\AdminNotice\\AdminNotices', 'Acme\\ConsumerB\\Settings' ), $request['records']['consumer_b']['resolved'] );
		self::assertStringContainsString( 'name="acme_cb_settings[greeting]"', $request['body'] );
	}

	public function test_the_build_is_a_production_install_that_keeps_the_bootstrap_loader_at_its_contract_path(): void {
		self::assertFileExists( self::FIXTURES . '/consumer-b/vendor-prefixed/ahegyes/wp-framework-bootstrap/load.php' );
		self::assertFileExists( self::FIXTURES . '/consumer-b/vendor/composer/installed.json' );

		$installed = \json_decode( (string) \file_get_contents( self::FIXTURES . '/consumer-b/vendor/composer/installed.json' ), true );
		self::assertIsArray( $installed );
		self::assertFalse( $installed['dev'] );
		self::assertSame( array(), $installed['packages'] );
	}
}
