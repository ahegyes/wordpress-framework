<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Core;

use DeepWebSolutions\Framework\PluginBoot;
use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger as PsrAbstractLogger;

final class RecordingLogger extends PsrAbstractLogger {
	public array $records = array();

	#[\Override]
	public function log( mixed $level, string|\Stringable $message, array $context = array() ): void {
		$this->records[] = array( $level, (string) $message, $context );
	}
}

#[CoversClass( PluginBoot::class )]
final class PluginBootTest extends TestCase {
	use CreatesUsersTrait;
	use IsolatesHooksTrait;

	protected const string PLUGIN_FILE = __DIR__ . '/../../Fixtures/Core/acme-boot.php';

	protected const string NOTICE = 'Acme Boot could not start, so its features are off. The error has been logged.';

	protected RecordingLogger $logger;

	protected array $fired = array();

	protected array $failures = array();

	#[\Override]
	protected function setUp(): void {
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
			'acme_initialization_failure',
			function ( ?\Throwable $failure ): void {
				$this->fired[]    = 'acme_initialization_failure';
				$this->failures[] = $failure;
			}
		);
	}

	#[\Override]
	protected function tearDown(): void {
		foreach ( array( 'composition failed', 'first failure', 'second failure' ) as $message ) {
			\delete_transient( 'acme_boot_failure_' . \md5( $message ) );
		}
		\wp_set_current_user( 0 );
	}

	public function test_a_composition_that_completes_boots_and_announces_initialization_once(): void {
		$boot = $this->make_boot();

		$boot->run( static fn (): bool => true );

		self::assertSame( 'booted', $boot->status );
		self::assertSame( array( 'acme_initialized' ), $this->fired );
	}

	public function test_a_composition_that_reports_an_unmet_requirement_is_blocked(): void {
		$boot = $this->make_boot();

		$boot->run( static fn (): bool => false );

		self::assertSame( 'blocked', $boot->status );
		self::assertSame( array( 'acme_initialization_failure' ), $this->fired );
		self::assertSame( array( null ), $this->failures );
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
		self::assertSame( 'first failure', $this->logger->records[0][2]['message'] );
	}

	public function test_distinct_failures_are_each_logged_once(): void {
		foreach ( array( 'first failure', 'second failure', 'first failure' ) as $message ) {
			$this->make_boot()->run( static fn (): bool => throw new \RuntimeException( $message ) );
		}

		self::assertSame( array( 'first failure', 'second failure' ), \array_map( static fn ( array $record ): string => $record[2]['message'], $this->logger->records ) );
	}

	#[Group( 'rethrow' )]
	public function test_a_failing_composition_is_rethrown_after_the_failure_action_in_a_local_environment(): void {
		self::assertSame( 'local', \wp_get_environment_type() );
		$failure = new \RuntimeException( 'composition failed' );
		$boot    = $this->make_boot();

		try {
			$boot->run( static fn (): bool => throw $failure );
			self::fail( 'The failure was not rethrown.' );
		} catch ( \RuntimeException $caught ) {
			self::assertSame( $failure, $caught );
		}

		self::assertSame( 'failed', $boot->status );
		self::assertSame( array( 'acme_initialization_failure' ), $this->fired );
		self::assertSame( array( $failure ), $this->failures );
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
