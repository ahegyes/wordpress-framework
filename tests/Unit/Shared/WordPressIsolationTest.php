<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Unit\Shared;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class WordPressIsolationTest extends TestCase {
	public function test_a_wordpress_call_in_shared_code_fails_the_shared_phpstan_config(): void {
		$root    = \dirname( __DIR__, 3 );
		$fixture = "$root/tests/Fixtures/shared/wordpress-call.php";
		$command = \implode(
			' ',
			\array_map( 'escapeshellarg', array( \PHP_BINARY, "$root/vendor/bin/phpstan", 'analyse', '--configuration', "$root/phpstan.shared.neon", '--error-format', 'json', '--no-progress', $fixture ) )
		);

		\exec( "$command 2>/dev/null", $output ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- The test runs the real PHPStan.
		$report = \json_decode( \implode( "\n", $output ), true, flags: \JSON_THROW_ON_ERROR );

		self::assertSame( array( 'function.notFound' ), \array_column( $report['files'][ $fixture ]['messages'] ?? array(), 'identifier' ) );
	}
}
