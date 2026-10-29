<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Unit\Core\Logging;

use DeepWebSolutions\Framework\Core\Logging\AbstractLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel as PsrLogLevel;

final class ThresholdLogger extends AbstractLogger {
	public static function threshold(): int {
		return self::get_default_threshold();
	}

	public static function severity( string $level ): int {
		return self::get_severity( $level );
	}

	#[\Override]
	public function log( mixed $level, string|\Stringable $message, array $context = array() ): void {}
}

#[CoversClass( AbstractLogger::class )]
final class AbstractLoggerTest extends TestCase {
	public function test_without_wp_debug_the_default_threshold_is_warning(): void {
		self::assertFalse( \defined( 'WP_DEBUG' ) );

		self::assertSame( ThresholdLogger::severity( PsrLogLevel::WARNING ), ThresholdLogger::threshold() );
	}
}
