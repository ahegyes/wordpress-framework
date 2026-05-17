<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Bootstrap\Tests\Integration;

use DeepWebSolutions\Framework\Bootstrap\Tests\Support\WritesPluginFixtures;
use PHPUnit\Framework\TestCase;

use function DeepWebSolutions\Framework\Bootstrap\Requirements\check_requirements;

final class CheckRequirementsTest extends TestCase {
	use WritesPluginFixtures;

	public function test_returns_true_when_runtime_satisfies_plugin_and_framework_mins(): void {
		$basename = 'dws-passes/dws-passes.php';
		$this->write_fixture(
			$basename,
			array(
				'Name'              => 'Passes',
				'Version'           => '1.0.0',
				'Requires PHP'      => '8.5',
				'Requires at least' => '7.0',
			)
		);

		self::assertTrue( check_requirements( $basename ) );
	}

	public function test_returns_wp_error_when_php_min_above_runtime(): void {
		$basename = 'dws-php-too-new/dws-php-too-new.php';
		$this->write_fixture(
			$basename,
			array(
				'Name'              => 'PHP Too New',
				'Version'           => '1.0.0',
				'Requires PHP'      => '99.99',
				'Requires at least' => '7.0',
			)
		);

		$result = check_requirements( $basename );

		self::assertInstanceOf( \WP_Error::class, $result );
		self::assertSame( array( 'plugin_php_incompatible' ), $result->get_error_codes() );

		$data = $result->get_error_data( 'plugin_php_incompatible' );
		self::assertSame( '99.99', $data['min'] );
		self::assertSame( PHP_VERSION, $data['current'] );
	}

	public function test_returns_wp_error_when_wp_min_above_runtime(): void {
		$basename = 'dws-wp-too-new/dws-wp-too-new.php';
		$this->write_fixture(
			$basename,
			array(
				'Name'              => 'WP Too New',
				'Version'           => '1.0.0',
				'Requires PHP'      => '8.5',
				'Requires at least' => '99.99',
			)
		);

		$result = check_requirements( $basename );

		self::assertInstanceOf( \WP_Error::class, $result );
		self::assertSame( array( 'plugin_wp_incompatible' ), $result->get_error_codes() );

		$data = $result->get_error_data( 'plugin_wp_incompatible' );
		self::assertSame( '99.99', $data['min'] );
		self::assertSame( $GLOBALS['wp_version'], $data['current'] );
	}

	public function test_returns_wp_error_with_both_codes_when_both_above_runtime(): void {
		$basename = 'dws-both-too-new/dws-both-too-new.php';
		$this->write_fixture(
			$basename,
			array(
				'Name'              => 'Both Too New',
				'Version'           => '1.0.0',
				'Requires PHP'      => '99.99',
				'Requires at least' => '99.99',
			)
		);

		$result = check_requirements( $basename );

		self::assertInstanceOf( \WP_Error::class, $result );
		self::assertContains( 'plugin_php_incompatible', $result->get_error_codes() );
		self::assertContains( 'plugin_wp_incompatible', $result->get_error_codes() );
	}

	public function test_framework_floor_subsumes_plugin_declaring_lower_php(): void {
		$basename = 'dws-php-low-min/dws-php-low-min.php';
		$this->write_fixture(
			$basename,
			array(
				'Name'              => 'PHP Low Min',
				'Version'           => '1.0.0',
				'Requires PHP'      => '5.0',
				'Requires at least' => '7.0',
			)
		);

		self::assertTrue( check_requirements( $basename ) );
	}

	public function test_framework_floor_applies_when_requires_php_header_missing(): void {
		$basename = 'dws-no-php-header/dws-no-php-header.php';
		$this->write_fixture(
			$basename,
			array(
				'Name'              => 'No PHP Header',
				'Version'           => '1.0.0',
				'Requires at least' => '7.0',
			)
		);

		self::assertTrue( check_requirements( $basename ) );
	}

	public function test_framework_floor_applies_when_plugin_file_does_not_exist(): void {
		self::assertTrue( check_requirements( 'dws-missing-plugin/dws-missing-plugin.php' ) );
	}

	public function test_framework_floor_applies_when_requires_wp_header_missing(): void {
		$basename = 'dws-no-wp-header/dws-no-wp-header.php';
		$this->write_fixture(
			$basename,
			array(
				'Name'         => 'No WP Header',
				'Version'      => '1.0.0',
				'Requires PHP' => '8.5',
			)
		);

		self::assertTrue( check_requirements( $basename ) );
	}
}
