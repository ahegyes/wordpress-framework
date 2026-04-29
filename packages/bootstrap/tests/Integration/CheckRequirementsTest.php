<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Bootstrap\Tests\Integration;

use PHPUnit\Framework\TestCase;

use function DeepWebSolutions\Framework\Bootstrap\check_requirements;
use function DeepWebSolutions\Framework\Bootstrap\get_plugin_metadata;
use function DeepWebSolutions\Framework\Bootstrap\is_php_compatible;
use function DeepWebSolutions\Framework\Bootstrap\is_wp_compatible;
use function DeepWebSolutions\Framework\Bootstrap\output_requirements_error;

use const DeepWebSolutions\Framework\Bootstrap\FRAMEWORK_MIN_PHP;
use const DeepWebSolutions\Framework\Bootstrap\FRAMEWORK_MIN_WP;

/**
 * Integration tests for check-requirements — runs inside the wp-env
 * `tests-cli` container with WordPress fully loaded. Exercises the happy
 * paths that delegate to real WP_Error / get_plugin_data / add_action /
 * wp_admin_notice. Run via `npm run test:integration`.
 */
final class CheckRequirementsTest extends TestCase {
	private string $plugin_basename = 'dws-framework-test-plugin/dws-framework-test-plugin.php';

	public function test_is_php_compatible_against_real_wp(): void {
		self::assertTrue( is_php_compatible( '5.0' ) );
		self::assertFalse( is_php_compatible( '99.99' ) );
	}

	public function test_is_wp_compatible_against_real_wp(): void {
		self::assertTrue( is_wp_compatible( '1.0' ) );
		self::assertFalse( is_wp_compatible( '99.99' ) );
	}

	public function test_get_plugin_metadata_reads_real_headers(): void {
		$metadata = get_plugin_metadata( $this->plugin_basename );

		self::assertSame( 'DWS Framework Test Plugin', $metadata['Name'] );
		self::assertSame( '1.2.3', $metadata['Version'] );
		self::assertSame( '8.5', $metadata['RequiresPHP'] );
		self::assertSame( '7.0', $metadata['RequiresWP'] );
	}

	public function test_check_requirements_matches_runtime_environment(): void {
		// Fixture declares PHP 8.5, WP 7.0 — same as the framework's own floor.
		// The result depends on the actual runtime: matrix entries with a
		// compatible WP/PHP get `true`; entries below the floor get a WP_Error
		// flagging which check failed (validates graceful failure behavior).
		$result = check_requirements( $this->plugin_basename );

		$env_compatible = \is_php_version_compatible( '8.5' )
			&& \is_wp_version_compatible( '7.0' );

		if ( $env_compatible ) {
			self::assertTrue( $result );
			return;
		}

		self::assertInstanceOf( \WP_Error::class, $result );
		$codes = $result->get_error_codes();
		self::assertTrue(
			\in_array( 'plugin_php_incompatible', $codes, true )
				|| \in_array( 'plugin_wp_incompatible', $codes, true ),
			'Expected at least one incompat code on an incompatible runtime.'
		);
	}

	public function test_check_requirements_returns_wp_error_when_php_below_floor(): void {
		$basename = 'dws-framework-test-plugin-too-new/dws-framework-test-plugin-too-new.php';
		$this->write_fixture(
			$basename,
			array(
				'Name'              => 'Too-New Test Plugin',
				'Version'           => '1.0.0',
				'Requires PHP'      => '99.99',
				'Requires at least' => '7.0',
			)
		);

		$result = check_requirements( $basename );

		self::assertInstanceOf( \WP_Error::class, $result );
		self::assertContains( 'plugin_php_incompatible', $result->get_error_codes() );
		$data = $result->get_error_data( 'plugin_php_incompatible' );
		self::assertSame( '99.99', $data['min'] );
		self::assertSame( PHP_VERSION, $data['current'] );
	}

	public function test_framework_min_acts_as_floor_when_plugin_declares_lower(): void {
		$basename = 'dws-framework-test-plugin-low-min/dws-framework-test-plugin-low-min.php';
		$this->write_fixture(
			$basename,
			array(
				'Name'              => 'Low-Min Test Plugin',
				'Version'           => '1.0.0',
				'Requires PHP'      => '5.0',
				'Requires at least' => '5.0',
			)
		);

		$result = check_requirements( $basename );

		// When the runtime meets framework floors, the effective min is
		// applied silently and the result is true. Otherwise, error data
		// must reflect the FRAMEWORK_* constants (not the plugin's lower
		// declared min).
		if ( true === $result ) {
			self::assertTrue( \is_php_version_compatible( FRAMEWORK_MIN_PHP ) );
			self::assertTrue( \is_wp_version_compatible( FRAMEWORK_MIN_WP ) );
			return;
		}

		self::assertInstanceOf( \WP_Error::class, $result );
		foreach ( $result->get_error_codes() as $code ) {
			$data = $result->get_error_data( $code );
			if ( 'plugin_php_incompatible' === $code ) {
				self::assertSame( FRAMEWORK_MIN_PHP, $data['min'] );
			} elseif ( 'plugin_wp_incompatible' === $code ) {
				self::assertSame( FRAMEWORK_MIN_WP, $data['min'] );
			}
		}
	}

	public function test_output_requirements_error_renders_via_wp_admin_notice(): void {
		$error = new \WP_Error();
		$error->add(
			'plugin_php_incompatible',
			'',
			array( 'min' => '8.5', 'current' => '7.4' )
		);
		$error->add(
			'plugin_wp_incompatible',
			'',
			array( 'min' => '7.0', 'current' => '6.5' )
		);

		output_requirements_error( $this->plugin_basename, $error );

		self::assertGreaterThan( 0, \has_action( 'admin_notices' ) );

		\ob_start();
		\do_action( 'admin_notices' );
		$output = (string) \ob_get_clean();

		self::assertStringContainsString( 'DWS Framework Test Plugin (version 1.2.3)', $output );
		self::assertStringContainsString( 'Requires PHP 8.5 or higher; you are running 7.4', $output );
		self::assertStringContainsString( 'Requires WordPress 7.0 or higher; you are running 6.5', $output );
	}

	/**
	 * @param array<string, string> $headers
	 */
	private function write_fixture( string $basename, array $headers ): void {
		$path = WP_PLUGIN_DIR . '/' . $basename;
		$dir  = \dirname( $path );
		if ( ! is_dir( $dir ) ) {
			\mkdir( $dir, 0755, true );
		}

		$lines = array( '<?php', '/**' );
		foreach ( $headers as $key => $value ) {
			$lines[] = ' * ' . $key . ': ' . $value;
		}
		$lines[] = ' */';

		\file_put_contents( $path, \implode( "\n", $lines ) . "\n" );
	}
}
