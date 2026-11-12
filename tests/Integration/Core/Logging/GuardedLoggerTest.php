<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Core\Logging;

use DeepWebSolutions\Framework\Core\Logging\GuardedLogger;
use DeepWebSolutions\Framework\Core\PluginIdentity;
use DeepWebSolutions\Framework\Tests\Integration\Support\RecordingLogger;
use DeepWebSolutions\Framework\Tests\Integration\Support\ThrowingLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\InvalidArgumentException as PsrInvalidArgumentException;
use Psr\Log\LogLevel as PsrLogLevel;

#[CoversClass( GuardedLogger::class )]
final class GuardedLoggerTest extends TestCase {
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

	public function test_a_record_passes_to_the_wrapped_logger_unchanged_and_skips_the_error_log(): void {
		$inner = new RecordingLogger();

		new GuardedLogger( $inner, new PluginIdentity( __FILE__, 'acme', 'acme' ) )->log( PsrLogLevel::INFO, 'Cache {name} is cold.', array( 'name' => 'acme' ) );

		self::assertSame( array( array( PsrLogLevel::INFO, 'Cache {name} is cold.', array( 'name' => 'acme' ) ) ), $inner->records );
		self::assertSame( '', \file_get_contents( $this->log_file ) );
	}

	public function test_a_record_whose_logger_throws_reaches_the_error_log_raw_with_its_context_as_json_and_the_logging_failure(): void {
		$exception = new \RuntimeException( 'Cache is cold.' );
		$failure   = new \UnexpectedValueException( 'The log is not writable.' );

		new GuardedLogger( new ThrowingLogger( $failure ), new PluginIdentity( __FILE__, 'acme', 'acme' ) )->error(
			'Warm-up of {name} failed.',
			array(
				'name'      => 'acme',
				'exception' => $exception,
			)
		);

		$log = (string) \file_get_contents( $this->log_file );
		self::assertStringContainsString( '[acme.error] Warm-up of {name} failed. {"name":"acme"}' . "\n" . 'RuntimeException: Cache is cold. in ' . __FILE__ . ':' . $exception->getLine(), $log );
		self::assertStringContainsString( 'The logger failed: UnexpectedValueException: The log is not writable. in ' . __FILE__ . ':' . $failure->getLine(), $log );
	}

	public function test_a_level_psr_3_does_not_define_throws_before_the_wrapped_logger_is_called(): void {
		$inner = new RecordingLogger();

		try {
			new GuardedLogger( $inner, new PluginIdentity( __FILE__, 'acme', 'acme' ) )->log( 'verbose', 'Cache is cold.' );
			self::fail( 'The level was accepted.' );
		} catch ( PsrInvalidArgumentException $exception ) {
			self::assertSame( "Log level 'verbose' is not a PSR-3 level.", $exception->getMessage() );
		}

		self::assertSame( array(), $inner->records );
		self::assertSame( '', \file_get_contents( $this->log_file ) );
	}
}
