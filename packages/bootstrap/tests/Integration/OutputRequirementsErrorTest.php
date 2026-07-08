<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Bootstrap\Tests\Integration;

use DeepWebSolutions\Framework\Bootstrap\Tests\Support\RendersAdminNotices;
use DeepWebSolutions\Framework\Bootstrap\Tests\Support\WritesPluginFixtures;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\UsesFunction;
use PHPUnit\Framework\TestCase;

use function DeepWebSolutions\Framework\Bootstrap\Notice\output_requirements_error;

#[CoversFunction( 'DeepWebSolutions\Framework\Bootstrap\Notice\output_requirements_error' )]
#[UsesFunction( 'DeepWebSolutions\Framework\Bootstrap\Plugin\get_plugin_metadata' )]
final class OutputRequirementsErrorTest extends TestCase {
	use RendersAdminNotices;
	use WritesPluginFixtures;

	private string $plugin_basename = 'dws-framework-test-plugin/dws-framework-test-plugin.php';

	public function test_renders_php_and_wp_incompat_messages(): void {
		$error = new \WP_Error();
		$error->add(
			'plugin_php_incompatible',
			'',
			array(
				'min'     => '8.5',
				'current' => '7.4',
			)
		);
		$error->add(
			'plugin_wp_incompatible',
			'',
			array(
				'min'     => '7.0',
				'current' => '6.5',
			)
		);

		output_requirements_error( $this->plugin_basename, $error );

		self::assertGreaterThan( 0, \has_action( 'all_admin_notices' ) );

		$output = $this->render_admin_notices();

		self::assertStringContainsString( 'DWS Framework Test Plugin (version 1.2.3)', $output );
		self::assertStringContainsString( 'Requires PHP 8.5 or higher; you are running 7.4', $output );
		self::assertStringContainsString( 'Requires WordPress 7.0 or higher; you are running 6.5', $output );
	}

	public function test_does_not_queue_notice_when_wp_error_has_no_codes(): void {
		output_requirements_error( $this->plugin_basename, new \WP_Error() );

		self::assertFalse( \has_action( 'all_admin_notices' ) );
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
			array(
				'min'     => '8.5',
				'current' => '7.4',
			)
		);

		output_requirements_error( $basename, $error );
		$output = $this->render_admin_notices();

		self::assertStringContainsString( $basename, $output );
	}

	public function test_renders_wp_only_incompat_message(): void {
		$error = new \WP_Error();
		$error->add(
			'plugin_wp_incompatible',
			'',
			array(
				'min'     => '7.0',
				'current' => '6.5',
			)
		);

		output_requirements_error( $this->plugin_basename, $error );
		$output = $this->render_admin_notices();

		self::assertStringContainsString( 'Requires WordPress 7.0 or higher; you are running 6.5', $output );
		self::assertStringNotContainsString( 'Requires PHP', $output );
	}

	public function test_preserves_sanctioned_name_markup(): void {
		$basename = 'dws-formatted-name/dws-formatted-name.php';
		$this->write_fixture(
			$basename,
			array(
				'Plugin Name' => 'Acme<em>!</em><script>alert(1)</script>',
				'Version'     => '1.0.0',
			)
		);

		$error = new \WP_Error();
		$error->add(
			'plugin_php_incompatible',
			'',
			array(
				'min'     => '8.5',
				'current' => '7.4',
			)
		);

		output_requirements_error( $basename, $error );
		$output = $this->render_admin_notices();

		// get_plugin_data() kses'd the Name to safe formatting tags; the notice keeps that
		// markup live (not escaped to visible text) while the disallowed <script> is dropped.
		self::assertStringContainsString( '<em>!</em>', $output );
		self::assertStringNotContainsString( '<script', $output );
	}
}
