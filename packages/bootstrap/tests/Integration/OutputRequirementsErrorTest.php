<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Bootstrap\Tests\Integration;

use DeepWebSolutions\Framework\Bootstrap\Tests\Support\WritesPluginFixtures;
use PHPUnit\Framework\TestCase;

use function DeepWebSolutions\Framework\Bootstrap\Notice\output_requirements_error;

final class OutputRequirementsErrorTest extends TestCase {
	use WritesPluginFixtures;

	private string $plugin_basename = 'dws-framework-test-plugin/dws-framework-test-plugin.php';

	public function test_renders_php_and_wp_incompat_messages(): void {
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

		$output = $this->render_admin_notices();

		self::assertStringContainsString( 'DWS Framework Test Plugin (version 1.2.3)', $output );
		self::assertStringContainsString( 'Requires PHP 8.5 or higher; you are running 7.4', $output );
		self::assertStringContainsString( 'Requires WordPress 7.0 or higher; you are running 6.5', $output );
	}

	public function test_does_not_queue_notice_when_wp_error_has_no_codes(): void {
		output_requirements_error( $this->plugin_basename, new \WP_Error() );

		self::assertFalse( \has_action( 'admin_notices' ) );
	}

	public function test_skips_rendering_when_wp_error_carries_only_unknown_codes(): void {
		$error = new \WP_Error();
		$error->add( 'some_custom_code', 'Custom failure.' );

		output_requirements_error( $this->plugin_basename, $error );

		self::assertSame( '', $this->render_admin_notices() );
	}

	public function test_falls_back_to_basename_when_metadata_name_absent(): void {
		$basename = 'dws-nameless-fixture/dws-nameless-fixture.php';
		$this->write_fixture(
			$basename,
			array(
				'Version' => '9.9.9',
			)
		);

		$error = new \WP_Error();
		$error->add(
			'plugin_php_incompatible',
			'',
			array( 'min' => '8.5', 'current' => '7.4' )
		);

		output_requirements_error( $basename, $error );
		$output = $this->render_admin_notices();

		self::assertStringContainsString( $basename, $output );
	}
}
