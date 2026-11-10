<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Unit\Core\Logging;

use DeepWebSolutions\Framework\Core\Logging\ErrorLogLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( ErrorLogLogger::class )]
final class ErrorLogLoggerTest extends TestCase {
	public function test_without_wp_debug_a_record_below_warning_is_dropped(): void {
		self::assertFalse( \defined( 'WP_DEBUG' ) );
		$log_file = (string) \tempnam( \sys_get_temp_dir(), 'acme-log-' );
		$previous = \ini_set( 'error_log', $log_file );

		try {
			new ErrorLogLogger( 'acme' )->info( 'Cache is cold.' );
			$log = \file_get_contents( $log_file );
		} finally {
			\ini_set( 'error_log', (string) $previous );
			\unlink( $log_file );
		}

		self::assertSame( '', $log );
	}
}
