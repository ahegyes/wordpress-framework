<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Consumers;

use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\InstallsConsumersTrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ConsumersCoexistenceTest extends TestCase {
	use CreatesUsersTrait;
	use InstallsConsumersTrait;

	protected const array ROOTS = array(
		'consumer_a' => array( 'Acme\\ConsumerA\\Scoped\\DeepWebSolutions\\Framework\\Core\\Scheduling\\Scheduler', 'Acme\\ConsumerA\\Scoped\\DeepWebSolutions\\Framework\\Core\\AdminNotice\\AdminNotices', 'Acme\\ConsumerA\\ProductData', 'Acme\\ConsumerA\\Exports' ),
		'consumer_b' => array( 'Acme\\ConsumerB\\Scoped\\DeepWebSolutions\\Framework\\Core\\AdminNotice\\AdminNotices', 'Acme\\ConsumerB\\Settings' ),
	);

	public static function activation_orders(): array {
		return array(
			'consumer-b-beside-consumer-a' => array( 'consumer-a', 'consumer-b' ),
			'consumer-a-beside-consumer-b' => array( 'consumer-b', 'consumer-a' ),
		);
	}

	public static function load_orders(): array {
		return array(
			'consumer-a-first' => array( array( 'consumer-a', 'consumer-b' ) ),
			'consumer-b-first' => array( array( 'consumer-b', 'consumer-a' ) ),
		);
	}

	#[DataProvider( 'activation_orders' )]
	public function test_activating_a_plugin_beside_the_running_other_includes_it_cleanly_and_keeps_both_active( string $active, string $activated ): void {
		$running = $this->install_consumer( $active, "acme-$active" );
		$plugin  = $this->install_consumer( $activated, "acme-$activated" );
		$this->store_active_plugins( array( $running, 'woocommerce/woocommerce.php' ) );

		$request = $this->consumer_request( 'GET', 'plugins.php?action=activate&plugin=' . \rawurlencode( $plugin ), $this->make_user( 'acme_admin', 'administrator' ), array( 'nonce' => "activate-plugin_$plugin" ) );

		self::assertNull( $request['died'] );
		self::assertStringContainsString( '/wp-admin/plugins.php?activate=true', (string) $request['location'] );
		self::assertSame( 'booted', $request['records'][ \str_replace( '-', '_', $active ) ]['status'] );
		self::assertTrue( $request['records'][ \str_replace( '-', '_', $activated ) ]['functions_loaded'] );
		self::assertSame( array( 'acme-consumer-a/acme-consumer-a.php', 'acme-consumer-b/acme-consumer-b.php', 'woocommerce/woocommerce.php' ), get_option( 'active_plugins' ) );
	}

	#[DataProvider( 'load_orders' )]
	public function test_both_plugins_boot_with_their_own_components_in_either_load_order( array $order ): void {
		$plugins = array();
		foreach ( $order as $fixture ) {
			$plugins[] = $this->install_consumer( $fixture, "acme-$fixture" );
		}
		$this->store_active_plugins( array( ...$plugins, 'woocommerce/woocommerce.php' ) );

		$request = $this->consumer_request( 'GET', 'index.php', $this->make_user( 'acme_admin', 'administrator' ), array() );

		self::assertNull( $request['died'] );
		self::assertStringContainsString( 'id="wpfooter"', $request['body'] );
		foreach ( self::ROOTS as $consumer => $roots ) {
			self::assertSame( 'booted', $request['records'][ $consumer ]['status'] );
			self::assertSame( 1, $request['records'][ $consumer ]['initialized'] );
			self::assertSame( $roots, $request['records'][ $consumer ]['resolved'] );
		}
	}
}
