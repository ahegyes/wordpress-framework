<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Core\Migration;

use DeepWebSolutions\Framework\Core\Migration\MigrationRunner;
use DeepWebSolutions\Framework\Core\Scheduling\Backends\WpCronBackend;
use DeepWebSolutions\Framework\Core\Scheduling\Scheduler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger as PsrNullLogger;

// WordPress caches the environment type while it loads, so each test loads it in a child process, which inherits the type this class sets.
#[CoversClass( MigrationRunner::class )]
#[RunTestsInSeparateProcesses]
final class MigrationRunnerInLocalEnvironmentTest extends TestCase {
	protected static string|false $environment_type = false;

	protected string $option;

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
		$this->option = 'acme_db_' . \bin2hex( \random_bytes( 4 ) );
	}

	#[\Override]
	protected function tearDown(): void {
		foreach ( array( '', '_error', '_high_water', '_first_run', '_lock' ) as $suffix ) {
			\delete_option( $this->option . $suffix );
		}
	}

	public function test_a_failing_step_releases_the_lock_and_is_rethrown_so_the_fixed_step_runs_next(): void {
		self::assertSame( 'local', \wp_get_environment_type() );
		$failure = new \RuntimeException( 'Step failed locally.' );
		$fixed   = false;
		$steps   = array(
			static function () use ( $failure, &$fixed ): void {
				if ( ! $fixed ) {
					throw $failure;
				}
			},
		);

		$caught = null;
		try {
			$this->make_runner( $steps )->run();
		} catch ( \RuntimeException $exception ) {
			$caught = $exception;
		}

		self::assertSame( $failure, $caught, 'The failure was not rethrown.' );
		self::assertNull( $this->get_lock() );
		self::assertIsArray( \get_option( "{$this->option}_error" ) );

		$fixed = true;
		$this->make_runner( $steps )->run();

		self::assertSame( 1, MigrationRunner::get_revision( $this->option ) );
		self::assertFalse( \get_option( "{$this->option}_error" ) );
	}

	public function test_a_failure_after_its_lock_was_taken_over_leaves_the_new_lock(): void {
		$steps = array(
			function (): void {
				global $wpdb;

				$wpdb->update( $wpdb->options, array( 'option_value' => '1' ), array( 'option_name' => "{$this->option}_lock" ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Stands in for another run taking the lock over.
				throw new \RuntimeException( 'Failed after a takeover.' );
			},
		);

		$caught = null;
		try {
			$this->make_runner( $steps )->run();
		} catch ( \RuntimeException $exception ) {
			$caught = $exception;
		}

		self::assertNotNull( $caught );
		self::assertSame( '1', $this->get_lock() );
	}

	protected function make_runner( array $steps ): MigrationRunner {
		return new MigrationRunner( __DIR__ . '/../../../Fixtures/Core/acme-boot.php', $this->option, $steps, new PsrNullLogger(), new Scheduler( 'acme', array( new WpCronBackend( 'acme' ) ) ) );
	}

	protected function get_lock(): ?string {
		global $wpdb;

		return $wpdb->get_var( $wpdb->prepare( 'SELECT option_value FROM %i WHERE option_name = %s', $wpdb->options, "{$this->option}_lock" ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reads the lock row past the options cache.
	}
}
