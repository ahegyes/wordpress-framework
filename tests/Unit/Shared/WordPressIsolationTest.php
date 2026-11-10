<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Unit\Shared;

use DeepWebSolutions\Framework\Tests\Support\RunsProcessesTrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class WordPressIsolationTest extends TestCase {
	use RunsProcessesTrait;

	public function test_a_wordpress_call_in_shared_code_fails_the_shared_phpstan_config(): void {
		$root    = \dirname( __DIR__, 3 );
		$fixture = "$root/tests/Fixtures/Shared/wordpress-call.php";

		list( , $output ) = self::run_process( array( \PHP_BINARY, "$root/vendor/bin/phpstan", 'analyse', '--configuration', "$root/phpstan.shared.neon", '--error-format', 'json', '--no-progress', $fixture ) );
		$report           = \json_decode( $output, true, flags: \JSON_THROW_ON_ERROR );

		self::assertSame( array( 'function.notFound' ), \array_column( $report['files'][ $fixture ]['messages'] ?? array(), 'identifier' ) );
	}
}
