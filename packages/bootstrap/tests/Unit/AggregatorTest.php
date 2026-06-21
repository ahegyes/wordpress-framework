<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Bootstrap\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AggregatorTest extends TestCase {
	public function test_aggregator_wires_every_concern_file(): void {
		self::assertTrue( \function_exists( 'DeepWebSolutions\Framework\Bootstrap\Environment\is_php_compatible' ) );
		self::assertTrue( \function_exists( 'DeepWebSolutions\Framework\Bootstrap\Plugin\get_plugin_metadata' ) );
		self::assertTrue( \function_exists( 'DeepWebSolutions\Framework\Bootstrap\Requirements\check_requirements' ) );
		self::assertTrue( \function_exists( 'DeepWebSolutions\Framework\Bootstrap\Notice\output_requirements_error' ) );
	}
}
