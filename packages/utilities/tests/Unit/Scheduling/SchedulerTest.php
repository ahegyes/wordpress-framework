<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Unit\Scheduling;

use DeepWebSolutions\Framework\Shared\Result\AbstractResult;
use DeepWebSolutions\Framework\Shared\Result\Success;
use DeepWebSolutions\Framework\Utilities\Scheduling\Scheduler;
use DeepWebSolutions\Framework\Utilities\Scheduling\SchedulerBackendInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( Scheduler::class )]
final class SchedulerTest extends TestCase {
	public function test_get_backend_returns_the_injected_backend(): void {
		$backend = $this->recording_backend();

		self::assertSame( $backend, ( new Scheduler( $backend ) )->get_backend() );
	}

	public function test_schedule_recurring_delegates_with_all_arguments(): void {
		$backend   = $this->recording_backend();
		$scheduler = new Scheduler( $backend );

		$result = $scheduler->schedule_recurring( 'dws_hook', 300, array( 1, 'a' ), 1700000000, 'grp' );

		self::assertSame(
			array( array( 'schedule_recurring', 'dws_hook', 300, array( 1, 'a' ), 1700000000, 'grp' ) ),
			$backend->calls,
		);
		self::assertSame( $backend->next_result, $result );
	}

	public function test_schedule_recurring_delegates_with_defaults(): void {
		$backend   = $this->recording_backend();
		$scheduler = new Scheduler( $backend );

		$scheduler->schedule_recurring( 'dws_hook', 300 );

		self::assertSame(
			array( array( 'schedule_recurring', 'dws_hook', 300, array(), null, '' ) ),
			$backend->calls,
		);
	}

	public function test_schedule_single_delegates_with_all_arguments(): void {
		$backend   = $this->recording_backend();
		$scheduler = new Scheduler( $backend );

		$result = $scheduler->schedule_single( 'dws_hook', 1700000000, array( 'x' ), 'grp' );

		self::assertSame(
			array( array( 'schedule_single', 'dws_hook', 1700000000, array( 'x' ), 'grp' ) ),
			$backend->calls,
		);
		self::assertSame( $backend->next_result, $result );
	}

	public function test_unschedule_delegates_with_all_arguments(): void {
		$backend   = $this->recording_backend();
		$scheduler = new Scheduler( $backend );

		$result = $scheduler->unschedule( 'dws_hook', array( 'x' ), 'grp' );

		self::assertSame(
			array( array( 'unschedule', 'dws_hook', array( 'x' ), 'grp' ) ),
			$backend->calls,
		);
		self::assertSame( $backend->next_result, $result );
	}

	public function test_is_scheduled_delegates_and_returns_the_backend_value(): void {
		$backend   = $this->recording_backend( true );
		$scheduler = new Scheduler( $backend );

		self::assertTrue( $scheduler->is_scheduled( 'dws_hook', array( 'x' ), 'grp' ) );
		self::assertSame(
			array( array( 'is_scheduled', 'dws_hook', array( 'x' ), 'grp' ) ),
			$backend->calls,
		);
	}

	public function test_get_next_scheduled_delegates_and_returns_the_backend_value(): void {
		$backend   = $this->recording_backend( false, 1700000000 );
		$scheduler = new Scheduler( $backend );

		self::assertSame( 1700000000, $scheduler->get_next_scheduled( 'dws_hook', array( 'x' ), 'grp' ) );
		self::assertSame(
			array( array( 'get_next_scheduled', 'dws_hook', array( 'x' ), 'grp' ) ),
			$backend->calls,
		);
	}

	/**
	 * @param   bool     $next_bool Value the fake returns from is_scheduled().
	 * @param   int|null $next_int  Value the fake returns from get_next_scheduled().
	 *
	 * @return SchedulerBackendInterface&object{calls: list<array<int, mixed>>, next_result: Success<true>}
	 */
	private function recording_backend( bool $next_bool = false, ?int $next_int = null ): SchedulerBackendInterface {
		return new class( $next_bool, $next_int ) implements SchedulerBackendInterface {
			/** @var list<array<int, mixed>> */
			public array $calls = array();

			/** @var Success<true> */
			public readonly Success $next_result;

			public function __construct(
				private readonly bool $next_bool,
				private readonly ?int $next_int,
			) {
				$this->next_result = Success::from( true );
			}

			/** @return Success<true> */
			public function schedule_recurring( string $hook, int $interval, array $args = array(), ?int $first_run_timestamp = null, string $group = '' ): AbstractResult {
				$this->calls[] = array( 'schedule_recurring', $hook, $interval, $args, $first_run_timestamp, $group );
				return $this->next_result;
			}

			/** @return Success<true> */
			public function schedule_single( string $hook, int $timestamp, array $args = array(), string $group = '' ): AbstractResult {
				$this->calls[] = array( 'schedule_single', $hook, $timestamp, $args, $group );
				return $this->next_result;
			}

			/** @return Success<true> */
			public function unschedule( string $hook, array $args = array(), string $group = '' ): AbstractResult {
				$this->calls[] = array( 'unschedule', $hook, $args, $group );
				return $this->next_result;
			}

			public function is_scheduled( string $hook, array $args = array(), string $group = '' ): bool {
				$this->calls[] = array( 'is_scheduled', $hook, $args, $group );
				return $this->next_bool;
			}

			public function get_next_scheduled( string $hook, array $args = array(), string $group = '' ): ?int {
				$this->calls[] = array( 'get_next_scheduled', $hook, $args, $group );
				return $this->next_int;
			}
		};
	}
}
