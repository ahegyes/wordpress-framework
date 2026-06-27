<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Unit\Scheduling;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\TestCase;

use function DeepWebSolutions\Framework\Utilities\Scheduling\action_scheduler_is_ready;

#[CoversFunction( 'DeepWebSolutions\Framework\Utilities\Scheduling\action_scheduler_is_ready' )]
final class ActionSchedulerReadyTest extends TestCase {
	public function test_not_ready_when_the_function_table_is_absent_even_after_init_fired(): void {
		self::assertFalse(
			action_scheduler_is_ready(
				static fn ( string $name ): bool => false,
				static fn ( string $hook ): int => 1,
			),
		);
	}

	public function test_not_ready_when_loaded_but_init_has_not_fired(): void {
		self::assertFalse(
			action_scheduler_is_ready(
				static fn ( string $name ): bool => true,
				static fn ( string $hook ): int => 0,
			),
		);
	}

	public function test_ready_when_loaded_and_init_has_fired(): void {
		self::assertTrue(
			action_scheduler_is_ready(
				static fn ( string $name ): bool => true,
				static fn ( string $hook ): int => 1,
			),
		);
	}
}
