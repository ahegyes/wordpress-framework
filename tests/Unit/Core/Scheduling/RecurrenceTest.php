<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Unit\Core\Scheduling;

use DeepWebSolutions\Framework\Scheduling\Recurrence;
use DeepWebSolutions\Framework\Shared\Exception\InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( Recurrence::class )]
final class RecurrenceTest extends TestCase {
	public function test_every_keeps_an_interval_of_a_minute_or_more(): void {
		self::assertSame( 60, Recurrence::every( 60 )->seconds );
	}

	public function test_every_rejects_an_interval_under_a_minute(): void {
		$this->expectException( InvalidArgumentException::class );

		Recurrence::every( 59 );
	}
}
