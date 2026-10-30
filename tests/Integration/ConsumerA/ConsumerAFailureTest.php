<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\ConsumerA;

use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\InstallsConsumersTrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ConsumerAFailureTest extends TestCase {
	use CreatesUsersTrait;
	use InstallsConsumersTrait;

	protected const string FAILURE_NOTICE = 'Acme Consumer A could not finish starting, so its features may be unavailable. The error has been logged.';

	public function test_a_component_constructor_that_throws_shows_the_failure_notice_and_registers_no_component_hook(): void {
		$plugin  = $this->install_consumer( 'consumer-a', 'acme-consumer-a' );
		$request = $this->consumer_request(
			'GET',
			'index.php',
			$this->make_user( 'acme_ca_admin', 'administrator' ),
			array(
				'activate'  => $plugin,
				'constants' => array( 'ACME_CA_FAULT' => 'construct' ),
			)
		);

		self::assertNull( $request['died'] );
		self::assertStringContainsString( 'id="wpfooter"', $request['body'] );
		self::assertStringContainsString( self::FAILURE_NOTICE, $request['body'] );
		self::assertSame( 'failed', $request['records']['consumer_a']['status'] );
		self::assertFalse( $request['records']['export_hooked'] );
	}

	public function test_a_register_hooks_throw_after_a_relaxation_keeps_the_safeguard_and_the_request_completes(): void {
		$plugin  = $this->install_consumer( 'consumer-a', 'acme-consumer-a' );
		$request = $this->consumer_request(
			'GET',
			'index.php',
			$this->make_user( 'acme_ca_admin', 'administrator' ),
			array(
				'activate'  => $plugin,
				'constants' => array( 'ACME_CA_FAULT' => 'register' ),
			)
		);

		self::assertNull( $request['died'] );
		self::assertStringContainsString( 'id="wpfooter"', $request['body'] );
		self::assertStringContainsString( self::FAILURE_NOTICE, $request['body'] );
		self::assertSame( 'failed', $request['records']['consumer_a']['status'] );
		self::assertSame( 0, $request['records']['consumer_a']['boot_succeeded'] );
		self::assertFalse( $request['records']['can_export'] );
	}

	public function test_a_missing_component_class_file_shows_the_failure_notice(): void {
		$plugin  = $this->install_consumer( 'consumer-a', 'acme-consumer-a', array( 'src/Exports.php' ) );
		$request = $this->consumer_request( 'GET', 'index.php', $this->make_user( 'acme_ca_admin', 'administrator' ), array( 'activate' => $plugin ) );

		self::assertNull( $request['died'] );
		self::assertStringContainsString( 'id="wpfooter"', $request['body'] );
		self::assertStringContainsString( self::FAILURE_NOTICE, $request['body'] );
		self::assertSame( 'failed', $request['records']['consumer_a']['status'] );
	}
}
