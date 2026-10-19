<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\WooCommerce;

use Automattic\WooCommerce\Internal\Admin\Logging\LogHandlerFileV2;
use DeepWebSolutions\Framework\Tests\Integration\Support\ProvidesLogMessagesTrait;
use DeepWebSolutions\Framework\WooCommerce\WooCommerceLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\InvalidArgumentException as PsrInvalidArgumentException;
use Psr\Log\LogLevel as PsrLogLevel;

#[CoversClass( WooCommerceLogger::class )]
final class WooCommerceLoggerTest extends TestCase {
	use ProvidesLogMessagesTrait;

	protected string $log_file;

	protected string|false $previous_log_file;

	protected \WC_Log_Handler_Interface $handler;

	#[\Override]
	protected function setUp(): void {
		$this->log_file          = (string) \tempnam( \sys_get_temp_dir(), 'acme-log-' );
		$this->previous_log_file = \ini_set( 'error_log', $this->log_file );
		$this->handler           = new class() implements \WC_Log_Handler_Interface {
			public array $records = array();

			public function handle( $timestamp, $level, $message, $context ) {
				$this->records[] = array( $level, $message, $context );

				return true;
			}
		};
	}

	#[\Override]
	protected function tearDown(): void {
		\ini_set( 'error_log', (string) $this->previous_log_file );
		\wp_delete_file( $this->log_file );
		\delete_option( 'woocommerce_logs_logging_enabled' );
		\delete_option( 'woocommerce_logs_level_threshold' );
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

	public static function thresholds(): array {
		return array(
			'error'   => array( PsrLogLevel::ERROR, array( PsrLogLevel::EMERGENCY, PsrLogLevel::ALERT, PsrLogLevel::CRITICAL, PsrLogLevel::ERROR ) ),
			'warning' => array( PsrLogLevel::WARNING, array( PsrLogLevel::EMERGENCY, PsrLogLevel::ALERT, PsrLogLevel::CRITICAL, PsrLogLevel::ERROR, PsrLogLevel::WARNING ) ),
			'debug'   => array( PsrLogLevel::DEBUG, \array_keys( self::levels() ) ),
		);
	}

	public static function dropping_settings(): array {
		return array(
			'logging-off'       => array( 'woocommerce_logs_logging_enabled', 'no' ),
			'threshold-above'   => array( 'woocommerce_logs_level_threshold', PsrLogLevel::CRITICAL ),
			'threshold-at-it'   => array( 'woocommerce_logs_level_threshold', PsrLogLevel::ERROR, false ),
			'logging-on-by-yes' => array( 'woocommerce_logs_logging_enabled', 'yes', false ),
		);
	}

	#[DataProvider( 'levels' )]
	public function test_every_level_reaches_woocommerces_log_under_wp_debug( string $level ): void {
		self::assertTrue( WP_DEBUG );

		$this->logger()->log( $level, 'Cache {name} is cold.', array( 'name' => 'acme' ) );

		self::assertSame( array( array( $level, 'Cache acme is cold.' ) ), $this->messages() );
	}

	#[DataProvider( 'thresholds' )]
	public function test_a_threshold_replaces_the_wp_debug_default( string $threshold, array $logged ): void {
		$logger = $this->logger( $threshold );

		foreach ( \array_keys( self::levels() ) as $level ) {
			$logger->log( $level, 'Cache is cold.' );
		}

		self::assertSame( $logged, \array_column( $this->messages(), 0 ) );
	}

	public function test_a_threshold_psr_3_does_not_define_is_rejected(): void {
		$this->expectException( PsrInvalidArgumentException::class );
		$this->expectExceptionMessage( "Log level 'verbose' is not a PSR-3 level." );

		new WooCommerceLogger( 'acme', 'verbose' );
	}

	public function test_a_level_psr_3_does_not_define_is_rejected(): void {
		$this->expectException( PsrInvalidArgumentException::class );
		$this->expectExceptionMessage( "Log level 'verbose' is not a PSR-3 level." );

		$this->logger()->log( 'verbose', 'Cache is cold.' );
	}

	public function test_every_record_carries_the_constructors_source_even_when_the_context_names_its_own(): void {
		$this->logger( null, 'dws-x' )->error( 'Cache is cold.', array( 'source' => 'other' ) );

		self::assertSame( 'dws-x', $this->handler->records[0][2]['source'] );
	}

	#[DataProvider( 'log_messages' )]
	public function test_a_record_interpolates_only_printable_context_values( string $message, array $context, string $expected ): void {
		$this->logger()->error( $message, $context );

		self::assertSame( array( array( PsrLogLevel::ERROR, $expected ) ), $this->messages() );
	}

	public function test_a_record_casts_only_the_context_values_its_message_uses(): void {
		$unused = new class() implements \Stringable {
			public int $casts = 0;

			public function __toString(): string {
				++$this->casts;

				return 'unused';
			}
		};

		$this->logger()->error(
			'Hello {name}.',
			array(
				'name'   => 'world',
				'unused' => $unused,
			)
		);

		self::assertSame( array( array( PsrLogLevel::ERROR, 'Hello world.' ) ), $this->messages() );
		self::assertSame( 0, $unused->casts );
	}

	public function test_an_exception_in_the_context_reaches_woocommerce_as_a_string_with_its_class_location_and_trace(): void {
		$exception = new \RuntimeException( 'Cache is cold.' );

		$this->logger()->error( 'Warm-up failed.', array( 'exception' => $exception ) );

		$logged = $this->handler->records[0][2]['exception'];
		self::assertIsString( $logged );
		self::assertStringContainsString( 'RuntimeException: Cache is cold. in ' . __FILE__ . ':' . $exception->getLine(), $logged );
		self::assertStringContainsString( 'Stack trace:', $logged );
	}

	public function test_a_context_value_that_cannot_be_encoded_reaches_woocommerces_file_format_as_its_type(): void {
		$this->handler = new class() extends LogHandlerFileV2 {
			public array $entries = array();

			public function handle( $timestamp, $level, $message, $context ) {
				$this->entries[] = self::format_entry( $timestamp, $level, $message, $context );

				return true;
			}
		};
		$unencodable   = new class() implements \JsonSerializable {
			public function jsonSerialize(): mixed {
				throw new \LogicException( 'Not encodable.' );
			}
		};

		$this->logger()->error(
			'Payment failed.',
			array(
				'order'   => 42,
				'gateway' => $unencodable,
				'amount'  => \NAN,
			)
		);

		self::assertCount( 1, $this->handler->entries );
		self::assertStringEndsWith( 'ERROR Payment failed. CONTEXT: {"order":42,"gateway":"JsonSerializable@anonymous","amount":"float"}', $this->handler->entries[0] );
	}

	#[DataProvider( 'dropping_settings' )]
	public function test_an_error_reaches_the_error_log_exactly_when_woocommerce_drops_it( string $option, string $value, bool $dropped = true ): void {
		\update_option( $option, $value );
		$exception = new \RuntimeException( 'Gateway timed out.' );

		$this->logger( null, 'acme', false )->error(
			'Payment {id} failed.',
			array(
				'id'        => 7,
				'exception' => $exception,
			)
		);

		$log = (string) \file_get_contents( $this->log_file );
		if ( $dropped ) {
			self::assertStringContainsString( '[acme.error] Payment 7 failed.' . \PHP_EOL . 'RuntimeException: Gateway timed out. in ' . __FILE__, $log );
		} else {
			self::assertStringNotContainsString( 'Payment 7 failed.', $log );
		}
	}

	public function test_a_record_below_error_never_reaches_the_error_log(): void {
		\update_option( 'woocommerce_logs_logging_enabled', 'no' );

		$this->logger( null, 'acme', false )->warning( 'Cache is cold.' );

		self::assertStringNotContainsString( 'Cache is cold.', (string) \file_get_contents( $this->log_file ) );
	}

	// WooCommerce's own logger applies its logging switch and threshold, unless the test pins the threshold to keep every record.
	protected function logger( ?string $threshold = null, string $source = 'acme', bool $keep_everything = true ): WooCommerceLogger {
		return new WooCommerceLogger( $source, $threshold, new \WC_Logger( array( $this->handler ), $keep_everything ? PsrLogLevel::DEBUG : null ) );
	}

	protected function messages(): array {
		return \array_map( static fn ( array $record ): array => array( $record[0], $record[1] ), $this->handler->records );
	}
}
