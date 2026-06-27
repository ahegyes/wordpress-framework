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
	public function test_schedule_recurring_routes_to_action_scheduler_when_ready(): void {
		$action_scheduler = $this->recording_backend();
		$wp_cron          = $this->recording_backend();
		$scheduler        = new Scheduler( $action_scheduler, $wp_cron, static fn (): bool => true );

		$result = $scheduler->schedule_recurring( 'dws_hook', 300, array( 1, 'a' ), 1700000000, 'grp' );

		self::assertSame(
			array( array( 'schedule_recurring', 'dws_hook', 300, array( 1, 'a' ), 1700000000, 'grp' ) ),
			$action_scheduler->calls,
		);
		self::assertSame( array(), $wp_cron->calls );
		self::assertSame( $action_scheduler->next_result, $result );
	}

	public function test_schedule_recurring_routes_to_wp_cron_when_not_ready(): void {
		$action_scheduler = $this->recording_backend();
		$wp_cron          = $this->recording_backend();
		$scheduler        = new Scheduler( $action_scheduler, $wp_cron, static fn (): bool => false );

		$result = $scheduler->schedule_recurring( 'dws_hook', 300 );

		self::assertSame(
			array( array( 'schedule_recurring', 'dws_hook', 300, array(), null, '' ) ),
			$wp_cron->calls,
		);
		self::assertSame( array(), $action_scheduler->calls );
		self::assertSame( $wp_cron->next_result, $result );
	}

	public function test_schedule_single_routes_to_action_scheduler_when_ready(): void {
		$action_scheduler = $this->recording_backend();
		$wp_cron          = $this->recording_backend();
		$scheduler        = new Scheduler( $action_scheduler, $wp_cron, static fn (): bool => true );

		$result = $scheduler->schedule_single( 'dws_hook', 1700000000, array( 'x' ), 'grp' );

		self::assertSame(
			array( array( 'schedule_single', 'dws_hook', 1700000000, array( 'x' ), 'grp' ) ),
			$action_scheduler->calls,
		);
		self::assertSame( array(), $wp_cron->calls );
		self::assertSame( $action_scheduler->next_result, $result );
	}

	public function test_schedule_single_routes_to_wp_cron_when_not_ready(): void {
		$action_scheduler = $this->recording_backend();
		$wp_cron          = $this->recording_backend();
		$scheduler        = new Scheduler( $action_scheduler, $wp_cron, static fn (): bool => false );

		$result = $scheduler->schedule_single( 'dws_hook', 1700000000 );

		self::assertSame(
			array( array( 'schedule_single', 'dws_hook', 1700000000, array(), '' ) ),
			$wp_cron->calls,
		);
		self::assertSame( array(), $action_scheduler->calls );
		self::assertSame( $wp_cron->next_result, $result );
	}

	public function test_unschedule_routes_to_action_scheduler_when_ready(): void {
		$action_scheduler = $this->recording_backend();
		$wp_cron          = $this->recording_backend();
		$scheduler        = new Scheduler( $action_scheduler, $wp_cron, static fn (): bool => true );

		$result = $scheduler->unschedule( 'dws_hook', array( 'x' ), 'grp' );

		self::assertSame(
			array( array( 'unschedule', 'dws_hook', array( 'x' ), 'grp' ) ),
			$action_scheduler->calls,
		);
		self::assertSame( array(), $wp_cron->calls );
		self::assertSame( $action_scheduler->next_result, $result );
	}

	public function test_unschedule_routes_to_wp_cron_when_not_ready(): void {
		$action_scheduler = $this->recording_backend();
		$wp_cron          = $this->recording_backend();
		$scheduler        = new Scheduler( $action_scheduler, $wp_cron, static fn (): bool => false );

		$result = $scheduler->unschedule( 'dws_hook' );

		self::assertSame(
			array( array( 'unschedule', 'dws_hook', array(), '' ) ),
			$wp_cron->calls,
		);
		self::assertSame( array(), $action_scheduler->calls );
		self::assertSame( $wp_cron->next_result, $result );
	}

	public function test_is_scheduled_routes_to_action_scheduler_when_ready(): void {
		$action_scheduler = $this->recording_backend( true );
		$wp_cron          = $this->recording_backend( false );
		$scheduler        = new Scheduler( $action_scheduler, $wp_cron, static fn (): bool => true );

		self::assertTrue( $scheduler->is_scheduled( 'dws_hook', array( 'x' ), 'grp' ) );
		self::assertSame(
			array( array( 'is_scheduled', 'dws_hook', array( 'x' ), 'grp' ) ),
			$action_scheduler->calls,
		);
		self::assertSame( array(), $wp_cron->calls );
	}

	public function test_is_scheduled_routes_to_wp_cron_when_not_ready(): void {
		$action_scheduler = $this->recording_backend( false );
		$wp_cron          = $this->recording_backend( true );
		$scheduler        = new Scheduler( $action_scheduler, $wp_cron, static fn (): bool => false );

		self::assertTrue( $scheduler->is_scheduled( 'dws_hook' ) );
		self::assertSame(
			array( array( 'is_scheduled', 'dws_hook', array(), '' ) ),
			$wp_cron->calls,
		);
		self::assertSame( array(), $action_scheduler->calls );
	}

	public function test_get_next_scheduled_routes_to_action_scheduler_when_ready(): void {
		$action_scheduler = $this->recording_backend( false, 1700000000 );
		$wp_cron          = $this->recording_backend( false, null );
		$scheduler        = new Scheduler( $action_scheduler, $wp_cron, static fn (): bool => true );

		self::assertSame( 1700000000, $scheduler->get_next_scheduled( 'dws_hook', array( 'x' ), 'grp' ) );
		self::assertSame(
			array( array( 'get_next_scheduled', 'dws_hook', array( 'x' ), 'grp' ) ),
			$action_scheduler->calls,
		);
		self::assertSame( array(), $wp_cron->calls );
	}

	public function test_get_next_scheduled_routes_to_wp_cron_when_not_ready(): void {
		$action_scheduler = $this->recording_backend( false, null );
		$wp_cron          = $this->recording_backend( false, 1700000111 );
		$scheduler        = new Scheduler( $action_scheduler, $wp_cron, static fn (): bool => false );

		self::assertSame( 1700000111, $scheduler->get_next_scheduled( 'dws_hook' ) );
		self::assertSame(
			array( array( 'get_next_scheduled', 'dws_hook', array(), '' ) ),
			$wp_cron->calls,
		);
		self::assertSame( array(), $action_scheduler->calls );
	}

	public function test_register_lifecycle_wires_both_backends(): void {
		$action_scheduler = $this->recording_backend();
		$wp_cron          = $this->recording_backend();
		$scheduler        = new Scheduler( $action_scheduler, $wp_cron, static fn (): bool => true );

		$scheduler->register_lifecycle();

		self::assertSame( 1, $action_scheduler->lifecycle_calls );
		self::assertSame( 1, $wp_cron->lifecycle_calls );
	}

	public function test_backend_is_selected_per_call_as_readiness_changes(): void {
		$action_scheduler = $this->recording_backend();
		$wp_cron          = $this->recording_backend();
		$ready            = false;
		$scheduler        = new Scheduler(
			$action_scheduler,
			$wp_cron,
			static function () use ( &$ready ): bool {
				return $ready;
			},
		);

		(void) $scheduler->schedule_single( 'first', 1700000000 );
		$ready = true;
		(void) $scheduler->schedule_single( 'second', 1700000001 );

		self::assertSame(
			array( array( 'schedule_single', 'second', 1700000001, array(), '' ) ),
			$action_scheduler->calls,
		);
		self::assertSame(
			array( array( 'schedule_single', 'first', 1700000000, array(), '' ) ),
			$wp_cron->calls,
		);
	}

	/**
	 * @param   bool     $next_bool Value the fake returns from is_scheduled().
	 * @param   int|null $next_int  Value the fake returns from get_next_scheduled().
	 *
	 * @return SchedulerBackendInterface&object{calls: list<array<int, mixed>>, lifecycle_calls: int, next_result: Success<true>}
	 */
	private function recording_backend( bool $next_bool = false, ?int $next_int = null ): SchedulerBackendInterface {
		return new class( $next_bool, $next_int ) implements SchedulerBackendInterface {
			/** @var list<array<int, mixed>> */
			public array $calls = array();

			public int $lifecycle_calls = 0;

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

			public function register_lifecycle(): void {
				++$this->lifecycle_calls;
			}
		};
	}
}
