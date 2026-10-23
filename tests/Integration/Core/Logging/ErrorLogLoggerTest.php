<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Core\Logging;

use DeepWebSolutions\Framework\Core\Logging\ErrorLogLogger;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\ProvidesLogMessagesTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\InvalidArgumentException as PsrInvalidArgumentException;
use Psr\Log\LogLevel as PsrLogLevel;

#[CoversClass( ErrorLogLogger::class )]
final class ErrorLogLoggerTest extends TestCase {
	use IsolatesHooksTrait;
	use ProvidesLogMessagesTrait;

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
		\wp_delete_file( $this->log_file );
	}

	public static function levels(): array {
		return array(
			'emergency' => array( PsrLogLevel::EMERGENCY ),
			'alert'     => array( PsrLogLevel::ALERT ),
			'critical'  => array( PsrLogLevel::CRITICAL ),
			'error'     => array( PsrLogLevel::ERROR ),
			'warning'   => array( PsrLogLevel::WARNING ),
			'notice'    => array( PsrLogLevel::NOTICE ),
			'info'      => array( PsrLogLevel::INFO ),
			'debug'     => array( PsrLogLevel::DEBUG ),
		);
	}

	#[DataProvider( 'levels' )]
	public function test_every_level_reaches_the_error_log_under_wp_debug( string $level ): void {
		self::assertTrue( WP_DEBUG );

		( new ErrorLogLogger( 'acme' ) )->log( $level, 'Cache {name} is cold.', array( 'name' => 'acme' ) );

		self::assertStringContainsString( "[acme.$level] Cache acme is cold.", (string) \file_get_contents( $this->log_file ) );
	}

	#[DataProvider( 'levels' )]
	public function test_every_record_fires_query_monitors_action_for_its_level( string $level ): void {
		$received = array();
		\add_action(
			"qm/$level",
			static function ( mixed ...$args ) use ( &$received ): void {
				$received[] = $args;
			},
			10,
			2
		);

		( new ErrorLogLogger( 'acme' ) )->log( $level, 'Cache {name} is cold.', array( 'name' => 'acme' ) );

		self::assertSame( array( array( 'Cache {name} is cold.', array( 'name' => 'acme' ) ) ), $received );
	}

	#[DataProvider( 'log_messages' )]
	public function test_a_record_interpolates_only_printable_context_values( string $message, array $context, string $expected ): void {
		( new ErrorLogLogger( 'acme' ) )->error( $message, $context );

		self::assertStringContainsString( "[acme.error] $expected" . \PHP_EOL, (string) \file_get_contents( $this->log_file ) );
	}

	public function test_a_record_casts_only_the_context_values_its_message_uses(): void {
		$unused = new class() implements \Stringable {
			public int $casts = 0;

			public function __toString(): string {
				++$this->casts;

				return 'unused';
			}
		};

		( new ErrorLogLogger( 'acme' ) )->error(
			'Hello {name}.',
			array(
				'name'   => 'world',
				'unused' => $unused,
			)
		);

		self::assertStringContainsString( '[acme.error] Hello world.', (string) \file_get_contents( $this->log_file ) );
		self::assertSame( 0, $unused->casts );
	}

	public function test_an_exception_in_the_context_is_logged_with_its_class_location_and_trace(): void {
		$exception = new \RuntimeException( 'Cache is cold.' );

		( new ErrorLogLogger( 'acme' ) )->error(
			'Warm-up failed: {message}',
			array(
				'message'   => $exception->getMessage(),
				'exception' => $exception,
			)
		);

		$log = (string) \file_get_contents( $this->log_file );
		self::assertStringContainsString( '[acme.error] Warm-up failed: Cache is cold.', $log );
		self::assertStringContainsString( 'RuntimeException: Cache is cold. in ' . __FILE__ . ':' . $exception->getLine(), $log );
		self::assertStringContainsString( 'Stack trace:', $log );
	}

	public function test_a_previous_exception_is_logged_with_the_exception(): void {
		$exception = new \RuntimeException( 'Warm-up failed.', 0, new \LogicException( 'Cache is cold.' ) );

		( new ErrorLogLogger( 'acme' ) )->error( 'Warm-up failed.', array( 'exception' => $exception ) );

		self::assertStringContainsString( 'LogicException: Cache is cold. in ' . __FILE__, (string) \file_get_contents( $this->log_file ) );
	}

	public function test_an_exception_context_value_that_is_not_a_throwable_is_ignored(): void {
		( new ErrorLogLogger( 'acme' ) )->error( 'Warm-up failed.', array( 'exception' => 'Cache is cold.' ) );

		self::assertStringNotContainsString( 'Cache is cold.', (string) \file_get_contents( $this->log_file ) );
	}

	public function test_a_level_psr_3_does_not_define_is_rejected(): void {
		$this->expectException( PsrInvalidArgumentException::class );
		$this->expectExceptionMessage( "Log level 'verbose' is not a PSR-3 level." );

		( new ErrorLogLogger( 'acme' ) )->log( 'verbose', 'Cache is cold.' );
	}
}
