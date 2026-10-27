<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\ConsumerA;

use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\InstallsConsumersTrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Group( 'multisite' )]
final class ConsumerAOnNetworkTest extends TestCase {
	use CreatesUsersTrait;
	use InstallsConsumersTrait;

	public function test_a_network_activated_plugin_blocked_by_woocommerce_keeps_its_schedules_and_shows_the_notice_in_the_site_s_language(): void {
		$super_admin = $this->make_user( 'acme_ca_super_admin', 'administrator' );
		grant_super_admin( $super_admin );
		$plugin  = $this->install_consumer( 'consumer-a', 'acme-consumer-a' );
		$network = get_site_option( 'active_sitewide_plugins', array() );
		// WordPress loads network-activated plugins before the request's fixture runs, so the activation is stored.
		update_site_option( 'active_sitewide_plugins', array( $plugin => \time() ) + $network );

		try {
			$request = $this->consumer_request(
				'GET',
				'index.php',
				$super_admin,
				array(
					'constants' => array( 'WC_VERSION' => '1.0.0' ),
					'locale'    => 'de_DE',
				)
			);
		} finally {
			update_site_option( 'active_sitewide_plugins', $network );
			revoke_super_admin( $super_admin );
		}

		self::assertSame( 'blocked', $request['records']['consumer_a']['status'] );
		self::assertTrue( $request['records']['schedules_hooked'] );
		self::assertStringContainsString( 'Acme Consumer A benötigt WooCommerce 11.2 oder neuer, daher bleiben seine Funktionen aus, bis WooCommerce aktualisiert ist.', $request['body'] );
	}
}
