<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Core\Utilities;

use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use DeepWebSolutions\Framework\Utilities\ErrorLogLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LogLevel;

#[CoversClass( ErrorLogLogger::class )]
final class ErrorLogLoggerTest extends TestCase {
	use IsolatesHooksTrait;

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
			'emergency' => array( LogLevel::EMERGENCY ),
			'alert'     => array( LogLevel::ALERT ),
			'critical'  => array( LogLevel::CRITICAL ),
			'error'     => array( LogLevel::ERROR ),
			'warning'   => array( LogLevel::WARNING ),
			'notice'    => array( LogLevel::NOTICE ),
			'info'      => array( LogLevel::INFO ),
			'debug'     => array( LogLevel::DEBUG ),
		);
	}

	#[DataProvider( 'levels' )]
	public function test_every_level_reaches_the_error_log_under_wp_debug( string $level ): void {
		self::assertTrue( WP_DEBUG );

		( new ErrorLogLogger() )->log( $level, 'Cache {name} is cold.', array( 'name' => 'acme' ) );

		self::assertStringContainsString( "[$level] Cache acme is cold.", (string) \file_get_contents( $this->log_file ) );
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

		( new ErrorLogLogger() )->log( $level, 'Cache {name} is cold.', array( 'name' => 'acme' ) );

		self::assertSame( array( array( 'Cache {name} is cold.', array( 'name' => 'acme' ) ) ), $received );
	}

	public function test_a_level_psr_3_does_not_define_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( "Log level 'verbose' is not a PSR-3 level." );

		( new ErrorLogLogger() )->log( 'verbose', 'Cache is cold.' );
	}
}
