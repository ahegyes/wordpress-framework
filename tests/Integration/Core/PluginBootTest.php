<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Core;

use DeepWebSolutions\Framework\PluginBoot;
use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger as PsrAbstractLogger;

final class RecordingLogger extends PsrAbstractLogger {
	public array $records = array();

	#[\Override]
	public function log( mixed $level, string|\Stringable $message, array $context = array() ): void {
		$this->records[] = array( $level, (string) $message, $context );
	}
}

final class ThrowingLogger extends PsrAbstractLogger {
	public function __construct(
		protected \Throwable $failure
	) {}

	#[\Override]
	public function log( mixed $level, string|\Stringable $message, array $context = array() ): void {
		throw $this->failure;
	}
}

#[CoversClass( PluginBoot::class )]
final class PluginBootTest extends TestCase {
	use CreatesUsersTrait;
	use IsolatesHooksTrait;

	protected const string PLUGIN_FILE = __DIR__ . '/../../Fixtures/Core/acme-boot.php';

	protected const string NOTICE = 'Acme Boot could not finish starting, so its features may be unavailable. The error has been logged.';

	protected RecordingLogger $logger;

	protected array $fired = array();

	protected array $failures = array();

	#[\Override]
	protected function setUp(): void {
		$this->delete_failure_transients();
		$this->logger = new RecordingLogger();
		\add_action(
			'acme_initialized',
			function (): void {
				$this->fired[] = 'acme_initialized';
			},
			10,
			0
		);
		\add_action(
			'acme_initialization_blocked',
			function (): void {
				$this->fired[] = 'acme_initialization_blocked';
			},
			10,
			0
		);
		\add_action(
			'acme_initialization_failure',
			function ( \Throwable $failure ): void {
				$this->fired[]    = 'acme_initialization_failure';
				$this->failures[] = $failure;
			}
		);
	}

	#[\Override]
	protected function tearDown(): void {
		$this->delete_failure_transients();
		\wp_set_current_user( 0 );
	}

	public function test_a_composition_that_completes_boots_and_announces_initialization_once(): void {
		$boot = $this->make_boot();

		$boot->run( static fn (): bool => true );

		self::assertSame( 'booted', $boot->status );
		self::assertSame( array( 'acme_initialized' ), $this->fired );
	}

	public function test_a_composition_that_reports_an_unmet_requirement_is_blocked(): void {
		// The all hook receives an action's own arguments, before do_action() pads an argumentless call with an empty string.
		$arguments = array();
		\add_action(
			'all',
			static function ( string $hook_name, mixed ...$args ) use ( &$arguments ): void {
				if ( 'acme_initialization_blocked' === $hook_name ) {
					$arguments[] = $args;
				}
			}
		);
		$boot = $this->make_boot();

		$boot->run( static fn (): bool => false );

		self::assertSame( 'blocked', $boot->status );
		self::assertSame( array( 'acme_initialization_blocked' ), $this->fired );
		self::assertSame( array( array() ), $arguments );
	}

	public function test_a_failing_composition_fails_and_tells_only_plugin_activators(): void {
		$failure = new \RuntimeException( 'composition failed' );
		$boot    = $this->make_boot();

		$boot->run( static fn (): bool => throw $failure );

		self::assertSame( 'failed', $boot->status );
		self::assertSame( array( 'acme_initialization_failure' ), $this->fired );
		self::assertSame( array( $failure ), $this->failures );

		\wp_set_current_user( $this->make_user( 'acme-boot-admin', 'administrator' ) );
		self::assertStringContainsString( self::NOTICE, $this->render_admin_notices() );

		\wp_set_current_user( $this->make_user( 'acme-boot-editor', 'editor' ) );
		self::assertStringNotContainsString( self::NOTICE, $this->render_admin_notices() );
	}

	public function test_the_same_failure_is_logged_once_an_hour(): void {
		for ( $request = 0; $request < 3; ++$request ) {
			$this->make_boot()->run( static fn (): bool => throw new \RuntimeException( 'first failure' ) );
		}

		self::assertCount( 1, $this->logger->records );
		self::assertSame( 'error', $this->logger->records[0][0] );
		self::assertSame( 'Boot failed; the plugin did not finish starting: {message}', $this->logger->records[0][1] );
		self::assertSame( 'first failure', $this->logger->records[0][2]['message'] );
	}

	public function test_distinct_failures_are_each_logged_once(): void {
		foreach ( array( 'first failure', 'second failure', 'first failure' ) as $message ) {
			$this->make_boot()->run( static fn (): bool => throw new \RuntimeException( $message ) );
		}

		self::assertSame( array( 'first failure', 'second failure' ), \array_map( static fn ( array $record ): string => $record[2]['message'], $this->logger->records ) );
	}

	public static function logging_failures(): array {
		return array(
			'exception' => array( new \UnexpectedValueException( 'The log is not writable.' ) ),
			'error'     => array( new \TypeError( 'The log handler is misconfigured.' ) ),
		);
	}

	#[DataProvider( 'logging_failures' )]
	public function test_a_failure_whose_logger_throws_still_reaches_the_error_log_and_is_announced( \Throwable $logging_failure ): void {
		$failure  = new \RuntimeException( 'composition failed' );
		$boot     = new PluginBoot( self::PLUGIN_FILE, 'acme', new ThrowingLogger( $logging_failure ) );
		$log_file = (string) \tempnam( \sys_get_temp_dir(), 'acme-log-' );
		$previous = \ini_set( 'error_log', $log_file );

		try {
			$boot->run( static fn (): bool => throw $failure );
			$log = (string) \file_get_contents( $log_file );
		} finally {
			\ini_set( 'error_log', (string) $previous );
			\wp_delete_file( $log_file );
		}

		self::assertSame( 'failed', $boot->status );
		self::assertSame( array( 'acme_initialization_failure' ), $this->fired );
		self::assertSame( array( $failure ), $this->failures );
		self::assertStringContainsString( '[acme.error] Boot failed; the plugin did not finish starting: composition failed', $log );
		self::assertStringContainsString( 'RuntimeException: composition failed in ' . __FILE__ . ':' . $failure->getLine(), $log );
		self::assertStringContainsString( $logging_failure::class . ': ' . $logging_failure->getMessage(), $log );

		\wp_set_current_user( $this->make_user( 'acme-boot-admin', 'administrator' ) );
		self::assertStringContainsString( self::NOTICE, $this->render_admin_notices() );
	}

	// An interrupted run leaves its throttle transients behind, which silence the next run's logging for up to an hour.
	protected function delete_failure_transients(): void {
		foreach ( array( 'composition failed', 'first failure', 'second failure' ) as $message ) {
			\delete_transient( 'acme_boot_failure_' . \md5( $message ) );
		}
	}

	protected function make_boot(): PluginBoot {
		return new PluginBoot( self::PLUGIN_FILE, 'acme', $this->logger );
	}

	protected function render_admin_notices(): string {
		\ob_start();
		\do_action( 'all_admin_notices' );

		return (string) \ob_get_clean();
	}
}
