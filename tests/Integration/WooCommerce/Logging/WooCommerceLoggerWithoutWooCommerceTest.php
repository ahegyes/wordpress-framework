<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\WooCommerce\Logging;

use DeepWebSolutions\Framework\WooCommerce\Logging\WooCommerceLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass( WooCommerceLogger::class )]
#[Group( 'without-woocommerce' )]
final class WooCommerceLoggerWithoutWooCommerceTest extends TestCase {
	protected string $log_file;

	protected string|false $previous_log_file;

	#[\Override]
	protected function setUp(): void {
		$this->log_file          = (string) \tempnam( \sys_get_temp_dir(), 'acme-log-' );
		$this->previous_log_file = \ini_set( 'error_log', $this->log_file );
	}

	#[\Override]
	protected function tearDown(): void {
		\ini_set( 'error_log', (string) $this->previous_log_file );
		wp_delete_file( $this->log_file );
	}

	public function test_without_woocommerce_errors_reach_the_error_log_and_lower_levels_go_nowhere(): void {
		self::assertFalse( \function_exists( 'wc_get_logger' ) );
		$logger = new WooCommerceLogger( 'acme' );

		$logger->warning( 'Cache is cold.' );
		$logger->error( 'Payment {id} failed.', array( 'id' => 7 ) );

		$log = (string) \file_get_contents( $this->log_file );
		self::assertStringContainsString( '[acme.error] Payment 7 failed.', $log );
		self::assertStringNotContainsString( 'Cache is cold.', $log );
	}
}
