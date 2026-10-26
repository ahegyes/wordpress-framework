<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Core\Scheduling;

use DeepWebSolutions\Framework\Core\Scheduling\Backends\ActionSchedulerBackend;
use DeepWebSolutions\Framework\Core\Scheduling\Backends\WpCronBackend;
use DeepWebSolutions\Framework\Core\Scheduling\Recurrence;
use DeepWebSolutions\Framework\Core\Scheduling\ScheduledHook;
use DeepWebSolutions\Framework\Core\Scheduling\Scheduler;
use DeepWebSolutions\Framework\Shared\Exception\InvalidArgumentException;
use DeepWebSolutions\Framework\Tests\Integration\Support\InterceptsQueriesTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\ReportsActionSchedulerVersionsTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( Scheduler::class )]
#[CoversClass( ActionSchedulerBackend::class )]
#[CoversClass( WpCronBackend::class )]
final class SchedulerTest extends TestCase {
	use InterceptsQueriesTrait;
	use IsolatesHooksTrait;
	use ReportsActionSchedulerVersionsTrait;

	protected const string GROUP = 'acme';

	protected string $hook;

	protected array $doing_it_wrong = array();

	protected array $writes = array();

	protected array $calls = array();

	#[\Override]
	protected function setUp(): void {
		$this->hook = 'acme_scheduling_' . \bin2hex( \random_bytes( 4 ) );
		add_filter( 'doing_it_wrong_trigger_error', '__return_false' );
		add_action(
			'doing_it_wrong_run',
			function ( string $function_name ): void {
				$this->doing_it_wrong[] = $function_name;
			}
		);
		add_action(
			'action_scheduler_stored_action',
			function ( int $action_id ): void {
				$this->writes[] = "stored $action_id";
			}
		);
		add_action(
			'action_scheduler_canceled_action',
			function ( int $action_id ): void {
				$this->writes[] = "canceled $action_id";
			}
		);
		add_filter(
			'pre_update_option_cron',
			function ( mixed $value, mixed $old_value ): mixed {
				if ( $value !== $old_value ) {
					$this->writes[] = 'cron';
				}

				return $value;
			},
			10,
			2
		);
	}

	#[\Override]
	protected function tearDown(): void {
		global $wpdb;

		wp_unschedule_hook( $this->hook );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE hook LIKE %s', $wpdb->actionscheduler_actions, $wpdb->esc_like( $this->hook ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Removes the rows the test created.
	}

	public static function schedulers(): array {
		return array(
			'action-scheduler' => array( 'production' ),
			'wp-cron'          => array( 'wp-cron' ),
		);
	}

	#[DataProvider( 'schedulers' )]
	public function test_enqueueing_twice_leaves_one_pending_invocation( string $kind ): void {
		$scheduler = $this->make_scheduler( $kind );
		$hook      = new ScheduledHook( $this->hook, array( 7 ) );

		self::assertTrue( $scheduler->enqueue( $hook ) );
		self::assertTrue( $scheduler->enqueue( $hook ) );

		self::assertSame( 'production' === $kind ? 1 : 0, \count( $this->pending_action_ids() ) );
		self::assertSame( 'production' === $kind ? 0 : 1, \count( $this->cron_events() ) );
		$ran = array();
		add_action(
			$this->hook,
			static function ( int $arg ) use ( &$ran ): void {
				$ran[] = $arg;
			}
		);
		'production' === $kind ? $this->run_due_actions() : $this->run_due_cron_events();
		self::assertSame( array( 7 ), $ran );
	}

	public function test_enqueueing_with_a_pending_wp_cron_invocation_adds_nothing_to_action_scheduler(): void {
		$hook = new ScheduledHook( $this->hook );
		$this->make_scheduler( 'wp-cron' )->enqueue( $hook );

		self::assertTrue( $this->make_scheduler( 'production' )->enqueue( $hook ) );

		self::assertSame( array(), $this->pending_action_ids() );
		self::assertCount( 1, $this->cron_events() );
	}

	public function test_enqueueing_inside_its_own_callback_counts_the_running_invocation(): void {
		$scheduler = $this->make_scheduler( 'production' );
		$hook      = new ScheduledHook( $this->hook );
		$scheduler->enqueue( $hook );
		add_action(
			$this->hook,
			function () use ( $scheduler, $hook ): void {
				$this->writes  = array();
				$this->calls[] = $scheduler->enqueue( $hook );
				$this->calls[] = $this->writes;
			}
		);

		$this->run_due_actions();

		self::assertSame( array( true, array() ), $this->calls );
		self::assertSame( array(), $this->pending_action_ids() );
	}

	public function test_a_callback_continues_itself_with_a_single_invocation_due_now(): void {
		$scheduler = $this->make_scheduler( 'production' );
		$hook      = new ScheduledHook( $this->hook );
		$scheduler->enqueue( $hook );
		$running = $this->pending_action_ids();
		$unique  = array();
		add_filter(
			'pre_as_schedule_single_action',
			static function ( mixed $pre, int $timestamp, string $name, array $args, string $group, int $priority, bool $is_unique ) use ( &$unique ): mixed {
				$unique[] = $is_unique;

				return $pre;
			},
			10,
			7
		);
		add_action(
			$this->hook,
			function () use ( $scheduler, $hook ): void {
				$this->calls[] = $scheduler->schedule_single( $hook, \time() );
			}
		);

		self::assertSame( 1, $this->run_due_actions() );

		self::assertSame( array( true ), $this->calls );
		self::assertSame( array( false ), $unique, 'Action Scheduler data stores differ on whether a running action refuses a unique insert.' );
		$pending = $this->pending_action_ids();
		self::assertCount( 1, $pending );
		self::assertNotSame( $running, $pending );
	}

	public function test_a_failed_continuation_insert_inside_its_own_callback_is_an_error(): void {
		$scheduler = $this->make_scheduler( 'production' );
		$hook      = new ScheduledHook( $this->hook );
		$scheduler->enqueue( $hook );
		add_action(
			$this->hook,
			function () use ( $scheduler, $hook ): void {
				add_filter( 'pre_as_schedule_single_action', '__return_zero' );
				$this->calls[] = $scheduler->schedule_single( $hook, \time() );
			}
		);

		$this->run_due_actions();

		self::assertCount( 1, $this->calls );
		self::assert_wp_error( $this->calls[0], 'scheduler_failed' );
		self::assertSame( array(), $this->pending_action_ids() );
	}

	public function test_an_action_scheduler_duplicate_of_a_running_invocation_is_success_without_a_write(): void {
		$scheduler = $this->make_scheduler( 'production' );
		$hook      = new ScheduledHook( $this->hook );
		$scheduler->enqueue( $hook );
		\ActionScheduler::store()->log_execution( $this->pending_action_ids()[0] );
		$this->writes = array();

		self::assertTrue( $scheduler->enqueue( $hook ) );

		self::assertSame( array(), $this->writes );
		self::assertSame( array(), $this->pending_action_ids() );
	}

	public function test_an_enqueue_that_races_another_request_adds_no_duplicate_on_action_scheduler(): void {
		$hook = $this->hook;
		$race = static function ( mixed $pre ) use ( $hook, &$race ): mixed {
			remove_filter( 'pre_as_enqueue_async_action', $race );
			as_enqueue_async_action( $hook, array(), self::GROUP );

			return $pre;
		};
		add_filter( 'pre_as_enqueue_async_action', $race );

		self::assertTrue( $this->make_scheduler( 'production' )->enqueue( new ScheduledHook( $this->hook ) ) );

		self::assertCount( 1, $this->pending_action_ids() );
	}

	public function test_an_action_scheduler_store_failure_is_an_error(): void {
		add_filter( 'pre_as_enqueue_async_action', '__return_zero' );

		$result = $this->make_scheduler( 'production' )->enqueue( new ScheduledHook( $this->hook ) );

		self::assert_wp_error( $result, 'scheduler_failed' );
	}

	public function test_a_throwing_scheduler_is_an_error(): void {
		add_filter(
			'pre_as_enqueue_async_action',
			static function (): never {
				throw new \RuntimeException( 'Store unavailable.' );
			}
		);

		$result = $this->make_scheduler( 'production' )->enqueue( new ScheduledHook( $this->hook ) );

		self::assert_wp_error( $result, 'scheduler_failed' );
		self::assertSame( 'Store unavailable.', $result->get_error_message() );
	}

	public static function writes(): array {
		return array(
			'enqueue'              => array( static fn ( Scheduler $scheduler, ScheduledHook $hook ): mixed => $scheduler->enqueue( $hook ) ),
			'schedule-single'      => array( static fn ( Scheduler $scheduler, ScheduledHook $hook ): mixed => $scheduler->schedule_single( $hook, \time() + 60 ) ),
			'ensure-recurring'     => array( static fn ( Scheduler $scheduler, ScheduledHook $hook ): mixed => $scheduler->ensure_recurring( $hook, Recurrence::from_interval( 3600 ) ) ),
			'clear-scheduled-hook' => array( static fn ( Scheduler $scheduler, ScheduledHook $hook ): mixed => $scheduler->clear_scheduled_hook( $hook ) ),
			'unschedule-hook'      => array( static fn ( Scheduler $scheduler, ScheduledHook $hook ): mixed => $scheduler->unschedule_hook( $hook->hook ) ),
		);
	}

	#[DataProvider( 'writes' )]
	public function test_a_write_before_action_scheduler_initializes_is_an_error_that_writes_nothing( \Closure $write ): void {
		$scheduler = $this->make_scheduler( 'production' );
		unset( $GLOBALS['wp_actions']['action_scheduler_init'] );
		$this->writes = array();

		$result = $write( $scheduler, new ScheduledHook( $this->hook ) );

		self::assert_wp_error( $result, 'scheduler_not_ready' );
		self::assertCount( 1, $this->doing_it_wrong );
		self::assertSame( array(), $this->writes );
	}

	#[DataProvider( 'writes' )]
	public function test_a_write_before_init_is_an_error_that_writes_nothing( \Closure $write ): void {
		$scheduler = $this->make_scheduler( 'wp-cron' );
		unset( $GLOBALS['wp_actions']['init'] );
		$this->writes = array();

		$result = $write( $scheduler, new ScheduledHook( $this->hook ) );

		self::assert_wp_error( $result, 'scheduler_not_ready' );
		self::assertCount( 1, $this->doing_it_wrong );
		self::assertSame( array(), $this->writes );
	}

	#[DataProvider( 'schedulers' )]
	public function test_ensuring_a_recurring_hook_creates_one_chain_and_then_writes_nothing( string $kind ): void {
		$scheduler = $this->make_scheduler( $kind );
		$hook      = new ScheduledHook( $this->hook );

		self::assertTrue( $scheduler->ensure_recurring( $hook, Recurrence::from_interval( 3600 ) ) );
		$this->writes = array();
		self::assertTrue( $scheduler->ensure_recurring( $hook, Recurrence::from_interval( 3600 ) ) );

		self::assertSame( array(), $this->writes );
		if ( 'production' === $kind ) {
			$pending = $this->pending_action_ids();
			self::assertCount( 1, $pending );
			self::assertSame( 3600, \ActionScheduler::store()->fetch_action( $pending[0] )->get_schedule()->get_recurrence() );
			self::assertSame( array(), $this->cron_events() );
		} else {
			$events = $this->cron_events();
			self::assertCount( 1, $events );
			self::assertSame( 'acme_every_3600s', $events[0][1]['schedule'] );
			self::assertSame( 3600, $events[0][1]['interval'] );
			self::assertSame( array(), $this->pending_action_ids() );
		}
	}

	#[DataProvider( 'schedulers' )]
	public function test_ensuring_counts_only_recurring_occurrences_as_chains( string $kind ): void {
		$scheduler = $this->make_scheduler( $kind );
		$hook      = new ScheduledHook( $this->hook );
		$scheduler->schedule_single( $hook, \time() + 7200 );

		self::assertTrue( $scheduler->ensure_recurring( $hook, Recurrence::from_interval( 3600 ) ) );
		$this->writes = array();
		self::assertTrue( $scheduler->ensure_recurring( $hook, Recurrence::from_interval( 3600 ) ) );

		self::assertSame( array(), $this->writes );
		if ( 'production' === $kind ) {
			$recurring = \array_filter( $this->pending_action_ids(), static fn ( int $action_id ): bool => \ActionScheduler::store()->fetch_action( $action_id )->get_schedule()->is_recurring() );
			self::assertCount( 2, $this->pending_action_ids() );
			self::assertCount( 1, $recurring );
		} else {
			self::assertSame( array( 'acme_every_3600s', false ), \array_map( static fn ( array $event ): string|false => $event[1]['schedule'], $this->cron_events() ) );
		}
	}

	public function test_ensuring_leaves_a_sole_wp_cron_chain_where_it_is(): void {
		$hook = new ScheduledHook( $this->hook );
		$this->make_scheduler( 'wp-cron' )->ensure_recurring( $hook, Recurrence::from_interval( 3600 ) );
		$this->writes = array();

		self::assertTrue( $this->make_scheduler( 'production' )->ensure_recurring( $hook, Recurrence::from_interval( 3600 ) ) );

		self::assertSame( array(), $this->writes );
		self::assertCount( 1, $this->cron_events() );
		self::assertSame( array(), $this->pending_action_ids() );
	}

	#[DataProvider( 'schedulers' )]
	public function test_ensuring_moves_an_existing_chain_to_the_requested_interval( string $kind ): void {
		$scheduler = $this->make_scheduler( $kind );
		$hook      = new ScheduledHook( $this->hook );
		$scheduler->ensure_recurring( $hook, Recurrence::from_interval( 3600 ) );

		self::assertTrue( $scheduler->ensure_recurring( $hook, Recurrence::from_interval( 7200 ) ) );

		if ( 'production' === $kind ) {
			$pending = $this->pending_action_ids();
			self::assertCount( 1, $pending );
			self::assertSame( 7200, \ActionScheduler::store()->fetch_action( $pending[0] )->get_schedule()->get_recurrence() );
		} else {
			self::assertSame( array( 'acme_every_7200s' ), \array_map( static fn ( array $event ): string => $event[1]['schedule'], $this->cron_events() ) );
		}
	}

	public function test_ensuring_moves_a_sole_wp_cron_chain_at_another_interval_onto_action_scheduler(): void {
		$hook = new ScheduledHook( $this->hook );
		$this->make_scheduler( 'wp-cron' )->ensure_recurring( $hook, Recurrence::from_interval( 3600 ) );

		self::assertTrue( $this->make_scheduler( 'production' )->ensure_recurring( $hook, Recurrence::from_interval( 7200 ) ) );

		self::assertSame( array(), $this->cron_events() );
		$pending = $this->pending_action_ids();
		self::assertCount( 1, $pending );
		self::assertSame( 7200, \ActionScheduler::store()->fetch_action( $pending[0] )->get_schedule()->get_recurrence() );
	}

	public function test_ensuring_with_a_chain_on_each_scheduler_leaves_one_on_action_scheduler(): void {
		$hook = new ScheduledHook( $this->hook );
		$this->make_scheduler( 'wp-cron' )->ensure_recurring( $hook, Recurrence::from_interval( 3600 ) );
		as_schedule_recurring_action( \time(), 3600, $this->hook, array(), self::GROUP );

		self::assertTrue( $this->make_scheduler( 'production' )->ensure_recurring( $hook, Recurrence::from_interval( 3600 ) ) );

		self::assertSame( array(), $this->cron_events() );
		self::assertCount( 1, $this->pending_action_ids() );
	}

	public function test_ensuring_while_a_recurring_occurrence_runs_creates_and_clears_nothing(): void {
		$hook = new ScheduledHook( $this->hook );
		$this->make_scheduler( 'wp-cron' )->ensure_recurring( $hook, Recurrence::from_interval( 3600 ) );
		$scheduler = $this->make_scheduler( 'production' );
		as_schedule_recurring_action( \time(), 3600, $this->hook, array(), self::GROUP );
		as_schedule_recurring_action( \time() + 3600, 3600, $this->hook, array(), self::GROUP );
		add_action(
			$this->hook,
			function () use ( $scheduler, $hook ): void {
				$this->writes  = array();
				$this->calls[] = $scheduler->ensure_recurring( $hook, Recurrence::from_interval( 7200 ) );
				$this->calls[] = $this->writes;
			}
		);

		self::assertSame( 1, $this->run_due_actions() );

		self::assertSame( array( true, array() ), $this->calls );
		self::assertCount( 1, $this->cron_events() );
	}

	public function test_a_recurring_wp_cron_write_needs_no_earlier_hook_registration(): void {
		$this->report_action_scheduler_versions( array( '3.9.0' ) );
		$scheduler = new Scheduler( self::GROUP );

		self::assertTrue( $scheduler->ensure_recurring( new ScheduledHook( $this->hook ), Recurrence::from_interval( 3900 ) ) );

		self::assertSame( array( 'acme_every_3900s' ), \array_map( static fn ( array $event ): string => $event[1]['schedule'], $this->cron_events() ) );
	}

	public function test_a_new_wp_cron_interval_survives_a_cron_run_in_a_later_request(): void {
		self::assertTrue( $this->make_scheduler( 'wp-cron' )->ensure_recurring( new ScheduledHook( $this->hook ), Recurrence::from_interval( 3700 ) ) );
		remove_all_filters( 'cron_schedules' ); // A later request starts without the schedules this request added.
		$this->make_scheduler( 'wp-cron' );
		$ran = 0;
		add_action(
			$this->hook,
			static function () use ( &$ran ): void {
				++$ran;
			}
		);

		$rescheduled = $this->run_due_cron_events();

		self::assertSame( 1, $ran );
		self::assertSame( array( true ), $rescheduled );
		$events = $this->cron_events();
		self::assertCount( 1, $events );
		self::assertGreaterThan( \time(), $events[0][0] );
		self::assertSame( 3700, $events[0][1]['interval'] );
	}

	public function test_clearing_a_scheduled_hook_removes_every_pending_occurrence_of_the_identity_in_the_group_only(): void {
		$hook = new ScheduledHook( $this->hook, array( 1 ) );
		as_enqueue_async_action( $this->hook, array( 1 ), self::GROUP );
		as_schedule_single_action( \time() + 3600, $this->hook, array( 1 ), self::GROUP );
		as_enqueue_async_action( $this->hook, array( 2 ), self::GROUP );
		as_enqueue_async_action( $this->hook, array( 1 ) );
		wp_schedule_single_event( \time() + 3600, $this->hook, array( 1 ) );
		wp_schedule_single_event( \time() + 7200, $this->hook, array( 1 ) );
		wp_schedule_single_event( \time() + 3600, $this->hook, array( 2 ) );

		self::assertTrue( $this->make_scheduler( 'production' )->clear_scheduled_hook( $hook ) );

		self::assertCount( 1, $this->pending_action_ids( array( 1 ) ), 'An occurrence outside the group survives.' );
		self::assertCount( 1, $this->pending_action_ids( array( 2 ) ) );
		self::assertSame( array( array( 2 ) ), \array_map( static fn ( array $event ): array => $event[1]['args'], $this->cron_events() ) );
	}

	public function test_an_invocation_that_survives_clearing_is_an_error(): void {
		$scheduler = $this->make_scheduler( 'wp-cron' );
		$hook      = new ScheduledHook( $this->hook );
		$scheduler->enqueue( $hook );
		add_filter( 'pre_clear_scheduled_hook', '__return_zero' );

		self::assert_wp_error( $scheduler->clear_scheduled_hook( $hook ), 'scheduler_failed' );
	}

	public function test_a_failed_clear_is_returned_by_clearing_a_scheduled_hook(): void {
		$scheduler = $this->make_scheduler( 'production' );
		$hook      = new ScheduledHook( $this->hook );
		wp_schedule_single_event( \time() + 3600, $this->hook );
		add_filter( 'pre_clear_scheduled_hook', static fn (): \WP_Error => new \WP_Error( 'acme_clear_failed' ) );

		self::assert_wp_error( $scheduler->clear_scheduled_hook( $hook ), 'acme_clear_failed' );
	}

	public function test_a_failed_clear_stops_collapsing_duplicate_chains(): void {
		$hook = new ScheduledHook( $this->hook );
		$this->make_scheduler( 'wp-cron' )->ensure_recurring( $hook, Recurrence::from_interval( 3600 ) );
		as_schedule_recurring_action( \time(), 3600, $this->hook, array(), self::GROUP );
		add_filter( 'pre_clear_scheduled_hook', static fn (): \WP_Error => new \WP_Error( 'acme_clear_failed' ) );
		$this->writes = array();

		$result = $this->make_scheduler( 'production' )->ensure_recurring( $hook, Recurrence::from_interval( 3600 ) );

		self::assert_wp_error( $result, 'acme_clear_failed' );
		self::assertSame( array(), \array_filter( $this->writes, static fn ( string $write ): bool => \str_starts_with( $write, 'stored' ) ) );
	}

	public function test_a_clear_that_leaves_a_chain_stops_collapsing_duplicate_chains(): void {
		$hook = new ScheduledHook( $this->hook );
		$this->make_scheduler( 'wp-cron' )->ensure_recurring( $hook, Recurrence::from_interval( 3600 ) );
		as_schedule_recurring_action( \time(), 3600, $this->hook, array(), self::GROUP );
		add_filter( 'pre_clear_scheduled_hook', '__return_zero' );
		$this->writes = array();

		$result = $this->make_scheduler( 'production' )->ensure_recurring( $hook, Recurrence::from_interval( 3600 ) );

		self::assert_wp_error( $result, 'scheduler_failed' );
		self::assertSame( array(), \array_filter( $this->writes, static fn ( string $write ): bool => \str_starts_with( $write, 'stored' ) ) );
	}

	public function test_a_failed_clear_is_returned_by_unscheduling_a_hook(): void {
		add_filter( 'pre_unschedule_hook', static fn (): \WP_Error => new \WP_Error( 'acme_clear_failed' ) );

		self::assert_wp_error( $this->make_scheduler( 'production' )->unschedule_hook( $this->hook ), 'acme_clear_failed' );
	}

	public function test_unscheduling_a_hook_on_action_scheduler_is_best_effort(): void {
		as_enqueue_async_action( $this->hook );
		$this->fail_queries( "a.hook='$this->hook'" );

		self::assertTrue( $this->make_scheduler( 'production' )->unschedule_hook( $this->hook ) );

		remove_all_filters( 'query' );
		self::assertCount( 1, $this->pending_action_ids() );
	}

	public function test_a_wp_cron_duplicate_event_is_success(): void {
		$backend = new WpCronBackend( self::GROUP );
		$hook    = new ScheduledHook( $this->hook );

		self::assertTrue( $backend->enqueue( $hook, 10, true ) );
		self::assertTrue( $backend->enqueue( $hook, 10, true ) );

		self::assertCount( 1, $this->cron_events() );
	}

	public function test_clearing_a_scheduled_hook_while_a_recurring_occurrence_runs_is_an_error(): void {
		$scheduler = $this->make_scheduler( 'production' );
		$hook      = new ScheduledHook( $this->hook );
		$scheduler->ensure_recurring( $hook, Recurrence::from_interval( 3600 ) );
		add_action(
			$this->hook,
			function () use ( $scheduler, $hook ): void {
				$this->calls[] = $scheduler->clear_scheduled_hook( $hook );
			}
		);

		$this->run_due_actions();

		self::assertCount( 1, $this->calls );
		self::assert_wp_error( $this->calls[0], 'scheduler_running' );
	}

	public function test_clearing_a_one_off_inside_its_own_callback_succeeds(): void {
		$scheduler = $this->make_scheduler( 'production' );
		$hook      = new ScheduledHook( $this->hook );
		$scheduler->enqueue( $hook );
		add_action(
			$this->hook,
			function () use ( $scheduler, $hook ): void {
				$this->calls[] = $scheduler->clear_scheduled_hook( $hook );
			}
		);

		$this->run_due_actions();

		self::assertSame( array( true ), $this->calls );
	}

	public function test_unscheduling_a_hook_removes_every_argument_variant_in_every_group(): void {
		as_enqueue_async_action( $this->hook, array(), self::GROUP );
		as_enqueue_async_action( $this->hook, array( 1 ), self::GROUP );
		as_enqueue_async_action( $this->hook, array( 'x' ) );
		as_enqueue_async_action( "{$this->hook}_other", array(), self::GROUP );
		wp_schedule_single_event( \time() + 3600, $this->hook );
		wp_schedule_single_event( \time() + 3600, $this->hook, array( 1 ) );

		self::assertTrue( $this->make_scheduler( 'production' )->unschedule_hook( $this->hook ) );

		self::assertSame( array(), $this->pending_action_ids() );
		self::assertSame( array(), $this->cron_events() );
		$other = array(
			'hook'   => "{$this->hook}_other",
			'status' => \ActionScheduler_Store::STATUS_PENDING,
		);
		self::assertCount( 1, as_get_scheduled_actions( $other, 'ids' ) );
	}

	public function test_is_scheduled_sees_a_pending_invocation_on_either_scheduler(): void {
		$scheduler = $this->make_scheduler( 'production' );
		$on_cron   = new ScheduledHook( $this->hook, array( 'cron' ) );
		$on_queue  = new ScheduledHook( $this->hook, array( 'queue' ) );
		wp_schedule_single_event( \time() + 3600, $this->hook, array( 'cron' ) );
		as_schedule_single_action( \time() + 3600, $this->hook, array( 'queue' ), self::GROUP );

		self::assertTrue( $scheduler->is_scheduled( $on_cron ) );
		self::assertTrue( $scheduler->is_scheduled( $on_queue ) );
		self::assertFalse( $scheduler->is_scheduled( new ScheduledHook( $this->hook ) ) );
	}

	public function test_is_scheduled_is_false_with_a_notice_before_action_scheduler_initializes(): void {
		$scheduler = $this->make_scheduler( 'production' );
		wp_schedule_single_event( \time() + 3600, $this->hook );
		unset( $GLOBALS['wp_actions']['action_scheduler_init'] );

		self::assertFalse( $scheduler->is_scheduled( new ScheduledHook( $this->hook ) ) );
		self::assertCount( 1, $this->doing_it_wrong );
	}

	public function test_is_scheduled_sees_a_running_action_scheduler_invocation(): void {
		$scheduler = $this->make_scheduler( 'production' );
		$hook      = new ScheduledHook( $this->hook );
		$scheduler->enqueue( $hook );
		\ActionScheduler::store()->log_execution( $this->pending_action_ids()[0] );

		self::assertTrue( $scheduler->is_scheduled( $hook ) );
	}

	#[DataProvider( 'schedulers' )]
	public function test_scheduling_a_single_invocation_twice_keeps_the_first( string $kind ): void {
		$scheduler = $this->make_scheduler( $kind );
		$hook      = new ScheduledHook( $this->hook );
		$first     = \time() + 3600;

		self::assertTrue( $scheduler->schedule_single( $hook, $first ) );
		self::assertTrue( $scheduler->schedule_single( $hook, $first + 86400 ) );

		if ( 'production' === $kind ) {
			$pending = $this->pending_action_ids();
			self::assertCount( 1, $pending );
			self::assertSame( $first, \ActionScheduler::store()->fetch_action( $pending[0] )->get_schedule()->get_date()->getTimestamp() );
		} else {
			$events = $this->cron_events();
			self::assertCount( 1, $events );
			self::assertSame( $first, $events[0][0] );
		}
	}

	public function test_scheduling_a_single_invocation_while_the_identity_runs_adds_a_pending_one(): void {
		$scheduler = $this->make_scheduler( 'production' );
		$hook      = new ScheduledHook( $this->hook );
		$scheduler->enqueue( $hook );
		\ActionScheduler::store()->log_execution( $this->pending_action_ids()[0] );

		self::assertTrue( $scheduler->schedule_single( $hook, \time() + 3600 ) );

		self::assertCount( 1, $this->pending_action_ids() );
	}

	public function test_a_timestamp_below_1_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );

		$this->make_scheduler( 'production' )->schedule_single( new ScheduledHook( $this->hook ), 0 );
	}

	#[DataProvider( 'schedulers' )]
	public function test_an_invocation_scheduled_in_the_past_runs_at_the_next_dispatch( string $kind ): void {
		$ran = 0;
		add_action(
			$this->hook,
			static function () use ( &$ran ): void {
				++$ran;
			}
		);

		self::assertTrue( $this->make_scheduler( $kind )->schedule_single( new ScheduledHook( $this->hook ), \time() - 60 ) );
		'production' === $kind ? $this->run_due_actions() : $this->run_due_cron_events();

		self::assertSame( 1, $ran );
	}

	public static function prioritized_writes(): array {
		return array(
			'enqueue'          => array( static fn ( Scheduler $scheduler, ScheduledHook $hook, int $priority ): mixed => $scheduler->enqueue( $hook, $priority ) ),
			'schedule-single'  => array( static fn ( Scheduler $scheduler, ScheduledHook $hook, int $priority ): mixed => $scheduler->schedule_single( $hook, \time() + 3600, $priority ) ),
			'ensure-recurring' => array( static fn ( Scheduler $scheduler, ScheduledHook $hook, int $priority ): mixed => $scheduler->ensure_recurring( $hook, Recurrence::from_interval( 3600 ), $priority ) ),
		);
	}

	#[DataProvider( 'prioritized_writes' )]
	public function test_a_priority_is_stored_on_the_action_scheduler_row( \Closure $write ): void {
		self::assertTrue( $write( $this->make_scheduler( 'production' ), new ScheduledHook( $this->hook ), 7 ) );

		self::assertSame( 7, \ActionScheduler::store()->fetch_action( $this->pending_action_ids()[0] )->get_priority() );
	}

	#[DataProvider( 'prioritized_writes' )]
	public function test_action_scheduler_clamps_a_priority_outside_0_to_255( \Closure $write ): void {
		$scheduler = $this->make_scheduler( 'production' );

		self::assertTrue( $write( $scheduler, new ScheduledHook( $this->hook, array( -1 ) ), -1 ) );
		self::assertTrue( $write( $scheduler, new ScheduledHook( $this->hook, array( 256 ) ), 256 ) );

		self::assertSame( 0, \ActionScheduler::store()->fetch_action( $this->pending_action_ids( array( -1 ) )[0] )->get_priority() );
		self::assertSame( 255, \ActionScheduler::store()->fetch_action( $this->pending_action_ids( array( 256 ) )[0] )->get_priority() );
	}

	#[DataProvider( 'prioritized_writes' )]
	public function test_wp_cron_ignores_the_priority( \Closure $write ): void {
		$scheduler = $this->make_scheduler( 'wp-cron' );

		self::assertTrue( $write( $scheduler, new ScheduledHook( $this->hook, array( -1 ) ), -1 ) );
		self::assertTrue( $write( $scheduler, new ScheduledHook( $this->hook, array( 256 ) ), 256 ) );

		self::assertCount( 2, $this->cron_events() );
	}

	public static function versions_below_the_floor(): array {
		return array(
			'previous-release'         => array( array( '4.0.0' ) ),
			'pre-release-of-the-floor' => array( array( '4.1.0-rc.1' ) ),
			'none-registered'          => array( array() ),
		);
	}

	#[DataProvider( 'versions_below_the_floor' )]
	public function test_action_scheduler_below_the_floor_takes_no_writes( array $versions ): void {
		$scheduler = $this->make_scheduler( 'production' );
		$this->report_action_scheduler_versions( $versions );

		self::assertTrue( $scheduler->enqueue( new ScheduledHook( $this->hook ) ) );

		self::assertSame( array(), $this->pending_action_ids() );
		self::assertCount( 1, $this->cron_events() );
	}

	public function test_enqueueing_counts_a_running_invocation_on_action_scheduler_below_the_floor(): void {
		as_enqueue_async_action( $this->hook, array(), self::GROUP );
		\ActionScheduler::store()->log_execution( $this->pending_action_ids()[0] );
		$scheduler = $this->make_scheduler( 'production' );
		$this->report_action_scheduler_versions( array( '4.0.0' ) );

		self::assertTrue( $scheduler->enqueue( new ScheduledHook( $this->hook ) ) );

		self::assertSame( array(), $this->cron_events() );
	}

	public function test_action_scheduler_below_the_floor_is_still_read_and_cleared(): void {
		$scheduler = $this->make_scheduler( 'production' );
		$hook      = new ScheduledHook( $this->hook );
		as_schedule_single_action( \time() + 3600, $this->hook, array(), self::GROUP );
		$this->report_action_scheduler_versions( array( '4.0.0' ) );

		self::assertTrue( $scheduler->is_scheduled( $hook ) );
		self::assertTrue( $scheduler->clear_scheduled_hook( $hook ) );

		self::assertSame( array(), $this->pending_action_ids() );
	}

	public function test_one_scheduler_follows_action_scheduler_readiness_from_call_to_call(): void {
		$scheduler = $this->make_scheduler( 'production' );
		$hook      = new ScheduledHook( $this->hook );
		$ready     = $GLOBALS['wp_actions']['action_scheduler_init'];
		unset( $GLOBALS['wp_actions']['action_scheduler_init'] );

		self::assert_wp_error( $scheduler->enqueue( $hook ), 'scheduler_not_ready' );
		$GLOBALS['wp_actions']['action_scheduler_init'] = $ready;
		self::assertTrue( $scheduler->enqueue( $hook ) );

		self::assertCount( 1, $this->pending_action_ids() );
	}

	public static function arguments(): array {
		$cases = array();
		foreach ( array( 'production', 'wp-cron' ) as $kind ) {
			$cases[ "$kind-every-scalar-kind" ]          = array( $kind, array( true, false, null, 0, 'Acme' ) );
			$cases[ "$kind-hashed-by-action-scheduler" ] = array( $kind, array( \str_repeat( 'a', 300 ) ) );
		}

		return $cases;
	}

	#[DataProvider( 'arguments' )]
	public function test_arguments_survive_storage_and_dispatch( string $kind, array $args ): void {
		$scheduler = $this->make_scheduler( $kind );
		$hook      = new ScheduledHook( $this->hook, $args );
		$received  = array();
		add_action(
			$this->hook,
			static function ( mixed ...$passed ) use ( &$received ): void {
				$received[] = $passed;
			},
			10,
			\count( $args )
		);

		$scheduler->enqueue( $hook );
		self::assertTrue( $scheduler->is_scheduled( $hook ) );
		self::assertFalse( $scheduler->is_scheduled( new ScheduledHook( $this->hook, array( 'other' ) ) ) );
		'production' === $kind ? $this->run_due_actions() : $this->run_due_cron_events();

		self::assertSame( array( $args ), $received );
	}

	protected static function assert_wp_error( mixed $actual, string $code ): void {
		self::assertInstanceOf( \WP_Error::class, $actual );
		self::assertSame( $code, $actual->get_error_code() );
	}

	// Action Scheduler below its floor leaves the writes to WP-Cron. A scheduler reads the version at each call, so the kind holds until the next call here.
	protected function make_scheduler( string $kind ): Scheduler {
		'wp-cron' === $kind ? $this->report_action_scheduler_versions( array( '3.9.0' ) ) : $this->restore_action_scheduler_versions();
		$scheduler = new Scheduler( self::GROUP );
		$scheduler->register_hooks();

		return $scheduler;
	}

	protected function pending_action_ids( ?array $args = null ): array {
		$query = array(
			'hook'     => $this->hook,
			'status'   => \ActionScheduler_Store::STATUS_PENDING,
			'per_page' => -1,
		);
		if ( ! \is_null( $args ) ) {
			$query['args'] = $args;
		}

		return \array_map( 'intval', as_get_scheduled_actions( $query, 'ids' ) );
	}

	protected function cron_events(): array {
		$events = array();
		foreach ( _get_cron_array() as $timestamp => $hooks ) {
			foreach ( $hooks[ $this->hook ] ?? array() as $event ) {
				$events[] = array( $timestamp, $event );
			}
		}

		return $events;
	}

	// Mirrors one Action Scheduler queue-runner batch.
	protected function run_due_actions(): int {
		$store = \ActionScheduler::store();
		$claim = $store->stake_claim( 10, null, array( $this->hook ) );
		foreach ( $claim->get_actions() as $action_id ) {
			\ActionScheduler::runner()->process_action( (int) $action_id, 'Tests' );
		}
		$store->release_claim( $claim );

		return \count( $claim->get_actions() );
	}

	// Mirrors one wp-cron.php run.
	protected function run_due_cron_events(): array {
		$rescheduled = array();
		foreach ( _get_cron_array() as $timestamp => $hooks ) {
			if ( $timestamp > \time() ) {
				break;
			}

			foreach ( $hooks[ $this->hook ] ?? array() as $event ) {
				if ( false !== $event['schedule'] ) {
					$rescheduled[] = wp_reschedule_event( $timestamp, $event['schedule'], $this->hook, $event['args'], true );
				}
				wp_unschedule_event( $timestamp, $this->hook, $event['args'], true );
				do_action_ref_array( $this->hook, $event['args'] );
			}
		}

		return $rescheduled;
	}
}
