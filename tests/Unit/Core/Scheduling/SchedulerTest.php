<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Unit\Core\Scheduling;

use DeepWebSolutions\Framework\Scheduling\Recurrence;
use DeepWebSolutions\Framework\Scheduling\ScheduledHook;
use DeepWebSolutions\Framework\Scheduling\Scheduler;
use DeepWebSolutions\Framework\Shared\Exception\InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( Scheduler::class )]
final class SchedulerTest extends TestCase {
	public static function valid_groups(): array {
		return array(
			'one-letter'        => array( 'a' ),
			'hook-prefix'       => array( 'dws_tpl' ),
			'mixed-case-hyphen' => array( 'Acme-2_tpl' ),
			'190-bytes'         => array( \str_repeat( 'a', 190 ) ),
		);
	}

	#[DataProvider( 'valid_groups' )]
	public function test_a_valid_group_constructs_a_scheduler( string $group ): void {
		self::assertInstanceOf( Scheduler::class, new Scheduler( $group ) );
	}

	public static function invalid_groups(): array {
		return array(
			'empty'          => array( '' ),
			'zero'           => array( '0' ),
			'leading-digit'  => array( '1acme' ),
			'space'          => array( 'acme tpl' ),
			'dot'            => array( 'acme.tpl' ),
			'trailing-break' => array( "acme\n" ),
			'191-bytes'      => array( \str_repeat( 'a', 191 ) ),
		);
	}

	#[DataProvider( 'invalid_groups' )]
	public function test_an_invalid_group_is_rejected( string $group ): void {
		$this->expectException( InvalidArgumentException::class );

		new Scheduler( $group );
	}

	public static function out_of_range_priorities(): array {
		$writes = array(
			'enqueue'          => static fn ( Scheduler $scheduler, ScheduledHook $hook, int $priority ): mixed => $scheduler->enqueue( $hook, $priority ),
			'schedule-at'      => static fn ( Scheduler $scheduler, ScheduledHook $hook, int $priority ): mixed => $scheduler->schedule_at( $hook, 1, $priority ),
			'ensure-recurring' => static fn ( Scheduler $scheduler, ScheduledHook $hook, int $priority ): mixed => $scheduler->ensure_recurring( $hook, Recurrence::every( 60 ), $priority ),
		);

		$cases = array();
		foreach ( $writes as $name => $write ) {
			$cases[ "$name-below-zero" ] = array( $write, -1 );
			$cases[ "$name-above-255" ]  = array( $write, 256 );
		}

		return $cases;
	}

	#[DataProvider( 'out_of_range_priorities' )]
	public function test_a_priority_outside_0_to_255_is_rejected( \Closure $write, int $priority ): void {
		$this->expectException( InvalidArgumentException::class );

		$write( new Scheduler( 'acme' ), new ScheduledHook( 'acme_sweep' ), $priority );
	}

	public function test_a_timestamp_below_1_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );

		( new Scheduler( 'acme' ) )->schedule_at( new ScheduledHook( 'acme_sweep' ), 0 );
	}

	public function test_unscheduling_all_rejects_an_empty_hook_name(): void {
		$this->expectException( InvalidArgumentException::class );

		( new Scheduler( 'acme' ) )->unschedule_all( '' );
	}
}
