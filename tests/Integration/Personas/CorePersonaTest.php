<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Personas;

use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\InstallsConsumersTrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Group( 'without-woocommerce' )]
final class CorePersonaTest extends TestCase {
	use CreatesUsersTrait;
	use InstallsConsumersTrait;

	public function test_the_plugin_boots_its_components_and_runs_its_migration_without_woocommerce(): void {
		$plugin  = $this->install_consumer( 'personas/core', 'acme-persona-core' );
		$request = $this->consumer_request( 'GET', 'index.php', $this->make_user( 'acme_pc_admin', 'administrator' ), array( 'activate' => $plugin ) );

		self::assertNull( $request['died'] );
		self::assertFalse( $request['records']['woocommerce_present'] );
		self::assertSame( 'booted', $request['records']['persona_core']['status'] );
		self::assertSame( array( 'Acme\\PersonaCore\\Scoped\\DeepWebSolutions\\Framework\\Core\\AdminNotice\\AdminNotices', 'Acme\\PersonaCore\\Greeting' ), $request['records']['persona_core']['resolved'] );
		self::assertSame( 1, $request['records']['persona_core']['initialized'] );
		self::assertTrue( $request['records']['greeting_shortcode'] );
		self::assertSame( array( 'greeting' => 'Hello' ), get_option( 'acme_pc_settings' ) );
	}
}
