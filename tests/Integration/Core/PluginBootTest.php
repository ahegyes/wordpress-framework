<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Core;

use DeepWebSolutions\Framework\Core\PluginBoot;
use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesStorageTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\RecordingLogger;
use DeepWebSolutions\Framework\Tests\Integration\Support\RendersAdminScreensTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\ThrowingLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( PluginBoot::class )]
final class PluginBootTest extends TestCase {
	use CreatesUsersTrait;
	use IsolatesHooksTrait;
	use IsolatesStorageTrait;
	use RendersAdminScreensTrait;

	protected const string PLUGIN_FILE = __DIR__ . '/../../Fixtures/Core/acme-boot.php';

	protected const string NOTICE = 'Acme Boot could not finish starting, so its features may be unavailable. The error has been logged.';

	protected RecordingLogger $logger;

	protected array $fired = array();

	protected array $failures = array();

	#[\Override]
	protected function setUp(): void {
		$this->logger = new RecordingLogger();
		add_action(
			"$this->hook_root/boot/succeeded",
			function (): void {
				$this->fired[] = 'succeeded';
			},
			10,
			0
		);
		add_action(
			"$this->hook_root/boot/blocked",
			function (): void {
				$this->fired[] = 'blocked';
			},
			10,
			0
		);
		add_action(
			"$this->hook_root/boot/failed",
			function ( \Throwable $failure ): void {
				$this->fired[]    = 'failed';
				$this->failures[] = $failure;
			}
		);
	}

	public function test_a_composition_that_completes_boots_and_announces_success_once(): void {
		$boot = $this->make_boot();

		$boot->run( static fn (): bool => true );

		self::assertSame( 'booted', $boot->status );
		self::assertSame( array( 'succeeded' ), $this->fired );
	}

	public function test_a_composition_named_as_a_callable_boots(): void {
		$boot = $this->make_boot();

		$boot->run( '__return_true' );

		self::assertSame( 'booted', $boot->status );
		self::assertSame( array( 'succeeded' ), $this->fired );
	}

	public function test_a_composition_that_reports_an_unmet_requirement_is_blocked(): void {
		// The all hook receives an action's own arguments, before do_action() pads an argumentless call with an empty string.
		$arguments = array();
		$blocked   = "$this->hook_root/boot/blocked";
		add_action(
			'all',
			static function ( string $hook_name, mixed ...$args ) use ( &$arguments, $blocked ): void {
				if ( $blocked === $hook_name ) {
					$arguments[] = $args;
				}
			}
		);
		$boot = $this->make_boot();

		$boot->run( static fn (): bool => false );

		self::assertSame( 'blocked', $boot->status );
		self::assertSame( array( 'blocked' ), $this->fired );
		self::assertSame( array( array() ), $arguments );
	}

	public function test_a_failing_composition_fails_and_tells_only_plugin_activators(): void {
		$failure = new \RuntimeException( 'composition failed' );
		$boot    = $this->make_boot();

		$boot->run( static fn (): bool => throw $failure );

		self::assertSame( 'failed', $boot->status );
		self::assertSame( array( 'failed' ), $this->fired );
		self::assertSame( array( $failure ), $this->failures );
		self::assertStringContainsString( self::NOTICE, $this->render_all_admin_notices( $this->make_user( 'acme-boot-admin', 'administrator' ) ) );
		self::assertStringNotContainsString( self::NOTICE, $this->render_all_admin_notices( $this->make_user( 'acme-boot-editor', 'editor' ) ) );
	}

	public function test_the_same_failure_is_logged_once_an_hour(): void {
		for ( $request = 0; $request < 3; ++$request ) {
			$this->make_boot()->run( static fn (): bool => throw new \RuntimeException( 'first failure' ) );
		}

		self::assertCount( 1, $this->logger->records );
		self::assertSame( 'error', $this->logger->records[0][0] );
		self::assertSame( 'Boot failed; the plugin did not finish starting: {message}', $this->logger->records[0][1] );
		self::assertSame( 'first failure', $this->logger->records[0][2]['message'] );
		self::assertEqualsWithDelta( \time() + HOUR_IN_SECONDS, (int) get_option( "_transient_timeout_{$this->prefix}_boot_failure_" . \md5( 'first failure' ) ), 5 );
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
		$boot     = new PluginBoot( self::PLUGIN_FILE, $this->slug, $this->prefix, new ThrowingLogger( $logging_failure ) );
		$log_file = (string) \tempnam( \sys_get_temp_dir(), 'acme-log-' );
		$previous = \ini_set( 'error_log', $log_file );

		try {
			$boot->run( static fn (): bool => throw $failure );
			$log = (string) \file_get_contents( $log_file );
		} finally {
			\ini_set( 'error_log', (string) $previous );
			wp_delete_file( $log_file );
		}

		self::assertSame( 'failed', $boot->status );
		self::assertSame( array( 'failed' ), $this->fired );
		self::assertSame( array( $failure ), $this->failures );
		self::assertStringContainsString( "[$this->prefix.error] Boot failed; the plugin did not finish starting: {message} {\"message\":\"composition failed\"}", $log );
		self::assertStringContainsString( 'RuntimeException: composition failed in ' . __FILE__ . ':' . $failure->getLine(), $log );
		self::assertStringContainsString( $logging_failure::class . ': ' . $logging_failure->getMessage(), $log );
		self::assertStringContainsString( self::NOTICE, $this->render_all_admin_notices( $this->make_user( 'acme-boot-admin', 'administrator' ) ) );
	}

	protected function make_boot(): PluginBoot {
		return new PluginBoot( self::PLUGIN_FILE, $this->slug, $this->prefix, $this->logger );
	}
}
