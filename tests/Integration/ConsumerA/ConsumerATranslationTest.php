<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\ConsumerA;

use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\InstallsConsumersTrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ConsumerATranslationTest extends TestCase {
	use CreatesUsersTrait;
	use InstallsConsumersTrait;

	public function test_the_failure_notice_shows_in_the_site_s_language_from_the_plugin_s_own_catalog(): void {
		$plugin  = $this->install_consumer( 'consumer-a', 'acme-consumer-a' );
		$request = $this->consumer_request(
			'GET',
			'index.php',
			$this->make_user( 'acme_ca_admin', 'administrator' ),
			array(
				'activate'  => $plugin,
				'constants' => array( 'ACME_CA_FAULT' => 'construct' ),
				'locale'    => 'de_DE',
			)
		);

		self::assertStringContainsString( 'Acme Consumer A konnte nicht vollständig starten, daher sind seine Funktionen möglicherweise nicht verfügbar. Der Fehler wurde protokolliert.', $request['body'] );
	}
}
