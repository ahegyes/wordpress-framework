<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Unit\Core\Utilities;

use DeepWebSolutions\Framework\Core\Clock\SystemClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( SystemClock::class )]
final class SystemClockTest extends TestCase {
	public function test_now_is_the_current_time(): void {
		$before = \microtime( true );
		$now    = ( new SystemClock() )->now();
		$after  = \microtime( true );

		self::assertGreaterThanOrEqual( $before, (float) $now->format( 'U.u' ) );
		self::assertLessThanOrEqual( $after, (float) $now->format( 'U.u' ) );
	}
}
