<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Support;

use Psr\Clock\ClockInterface as PsrClockInterface;

final class SettableClock implements PsrClockInterface {
	public function __construct(
		public \DateTimeImmutable $now
	) {}

	#[\Override]
	public function now(): \DateTimeImmutable {
		return $this->now;
	}

	public function advance( int $seconds ): void {
		$this->now = $this->now->modify( "+$seconds seconds" );
	}
}
