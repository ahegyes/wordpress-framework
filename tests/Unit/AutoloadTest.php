<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class AutoloadTest extends TestCase {
	public function test_every_class_follows_the_psr_4_mapping_of_its_file(): void {
		$command = \implode(
			' ',
			\array_map( 'escapeshellarg', array( 'composer', 'dump-autoload', '--optimize', '--strict-psr', '--dry-run', '--no-interaction', '--working-dir', \dirname( __DIR__, 2 ) ) )
		);

		\exec( "$command 2>&1", $output, $exit_code ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- The test runs the real Composer.

		self::assertSame( 0, $exit_code, \implode( "\n", $output ) );
	}
}
