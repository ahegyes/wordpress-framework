<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Core;

use DeepWebSolutions\Framework\Core\PluginBoot;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger as PsrNullLogger;

// WordPress caches the environment type while it loads, so each test loads it in a child process, which inherits the type this class sets.
#[CoversClass( PluginBoot::class )]
#[RunTestsInSeparateProcesses]
final class PluginBootInLocalEnvironmentTest extends TestCase {
	protected static string|false $environment_type = false;

	protected array $fired = array();

	protected array $failures = array();

	#[\Override]
	public static function setUpBeforeClass(): void {
		self::$environment_type = \getenv( 'WP_ENVIRONMENT_TYPE' );
		\putenv( 'WP_ENVIRONMENT_TYPE=local' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- The child processes inherit it.
	}

	#[\Override]
	public static function tearDownAfterClass(): void {
		\putenv( false === self::$environment_type ? 'WP_ENVIRONMENT_TYPE' : 'WP_ENVIRONMENT_TYPE=' . self::$environment_type ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Restores the outer value.
	}

	#[\Override]
	protected function setUp(): void {
		add_action(
			'acme/boot/succeeded',
			function (): void {
				$this->fired[] = 'acme/boot/succeeded';
			},
			10,
			0
		);
		add_action(
			'acme/boot/failed',
			function ( \Throwable $failure ): void {
				$this->fired[]    = 'acme/boot/failed';
				$this->failures[] = $failure;
			}
		);
	}

	#[\Override]
	protected function tearDown(): void {
		delete_transient( 'acme_boot_failure_' . \md5( 'composition failed' ) );
	}

	public function test_a_failing_composition_is_rethrown_after_the_failure_action(): void {
		self::assertSame( 'local', wp_get_environment_type() );
		$failure = new \RuntimeException( 'composition failed' );
		$boot    = new PluginBoot( __DIR__ . '/../../Fixtures/Core/acme-boot.php', 'acme', new PsrNullLogger() );

		$caught = null;
		try {
			$boot->run( static fn (): bool => throw $failure );
		} catch ( \RuntimeException $exception ) {
			$caught = $exception;
		}

		self::assertSame( $failure, $caught, 'The failure was not rethrown.' );
		self::assertSame( 'failed', $boot->status );
		self::assertSame( array( 'acme/boot/failed' ), $this->fired );
		self::assertSame( array( $failure ), $this->failures );
	}
}
