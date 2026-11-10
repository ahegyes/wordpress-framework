<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Unit;

use DeepWebSolutions\Framework\Tests\Support\RunsProcessesTrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class AutoloadTest extends TestCase {
	use RunsProcessesTrait;

	public function test_every_class_follows_the_psr_4_mapping_of_its_file(): void {
		list( $exit_code, $output, $errors ) = self::run_process( array( 'composer', 'dump-autoload', '--optimize', '--strict-psr', '--dry-run', '--no-interaction', '--working-dir', \dirname( __DIR__, 2 ) ) );

		self::assertSame( 0, $exit_code, $output . $errors );
	}
}
