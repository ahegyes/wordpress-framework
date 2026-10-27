<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Personas;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class SharedPersonaTest extends TestCase {
	protected const string BUILD = __DIR__ . '/../../Fixtures/personas/shared';

	// The script runs in its own PHP process, so WordPress never loads beside the scoped shared package.
	public function test_the_scoped_package_parses_versions_matches_results_and_compares_value_objects_in_plain_php(): void {
		self::assertFileExists( self::BUILD . '/vendor-prefixed/scoper-autoload.php', 'Build personas/shared first with `composer fixtures:build`.' );

		$descriptors = array(
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		);
		$process     = \proc_open( array( \PHP_BINARY, self::BUILD . '/run.php' ), $descriptors, $pipes ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open -- Runs the script without WordPress.
		self::assertIsResource( $process );
		$output = (string) \stream_get_contents( $pipes[1] );
		$errors = (string) \stream_get_contents( $pipes[2] );
		\fclose( $pipes[1] );
		\fclose( $pipes[2] );
		self::assertSame( 0, \proc_close( $process ), $errors . $output );

		self::assertSame(
			array(
				'wordpress' => false,
				'version'   => array( '2.1.0-rc.1', 1 ),
				'results'   => array( 'found acme', 'unknown acme' ),
				'equality'  => array( true, false ),
			),
			\json_decode( $output, true, flags: \JSON_THROW_ON_ERROR )
		);
	}
}
