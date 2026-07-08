<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Unit\Scheduling;

use DeepWebSolutions\Framework\Shared\Result\Failure;
use DeepWebSolutions\Framework\Shared\Result\Success;
use DeepWebSolutions\Framework\Utilities\Scheduling\Errors\SchedulingError;
use DeepWebSolutions\Framework\Utilities\Scheduling\Exceptions\InvalidSchedulerConfigurationException;
use DeepWebSolutions\Framework\Utilities\Scheduling\Scheduler;
use DeepWebSolutions\Framework\Utilities\Scheduling\SchedulerBackendInterface;
use DeepWebSolutions\Framework\Utilities\Scheduling\SchedulingErrorReason;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( Scheduler::class )]
#[UsesClass( Success::class )]
#[UsesClass( Failure::class )]
#[UsesClass( SchedulingError::class )]
#[UsesClass( SchedulingErrorReason::class )]
final class SchedulerTest extends TestCase {
	public function test_constructor_rejects_an_empty_backend_list(): void {
		$this->expectException( InvalidSchedulerConfigurationException::class );

		new Scheduler( array() );
	}

	public function test_schedule_recurring_routes_to_the_first_ready_backend(): void {
		$action_scheduler = $this->recording_backend();
		$wp_cron          = $this->recording_backend();
		$scheduler        = new Scheduler( array( $action_scheduler, $wp_cron ) );

		$result = $scheduler->schedule_recurring( 'dws_hook', 300, array( 1, 'a' ), 1700000000, 'grp' );

		self::assertSame(
			array( array( 'schedule_recurring', 'dws_hook', 300, array( 1, 'a' ), 1700000000, 'grp' ) ),
			$action_scheduler->calls,
		);
		self::assertSame( array(), $wp_cron->calls );
		self::assertSame( $action_scheduler->next_result, $result );
	}

	public function test_schedule_recurring_skips_a_not_ready_backend(): void {
		$action_scheduler = $this->recording_backend( ready: false );
		$wp_cron          = $this->recording_backend();
		$scheduler        = new Scheduler( array( $action_scheduler, $wp_cron ) );

		$result = $scheduler->schedule_recurring( 'dws_hook', 300 );

		self::assertSame(
			array( array( 'schedule_recurring', 'dws_hook', 300, array(), null, '' ) ),
			$wp_cron->calls,
		);
		self::assertSame( array(), $action_scheduler->calls );
		self::assertSame( $wp_cron->next_result, $result );
	}

	public function test_schedule_single_routes_to_the_first_ready_backend(): void {
		$action_scheduler = $this->recording_backend();
		$wp_cron          = $this->recording_backend();
		$scheduler        = new Scheduler( array( $action_scheduler, $wp_cron ) );

		$result = $scheduler->schedule_single( 'dws_hook', 1700000000, array( 'x' ), 'grp' );

		self::assertSame(
			array( array( 'schedule_single', 'dws_hook', 1700000000, array( 'x' ), 'grp' ) ),
			$action_scheduler->calls,
		);
		self::assertSame( array(), $wp_cron->calls );
		self::assertSame( $action_scheduler->next_result, $result );
	}

	public function test_schedule_single_skips_a_not_ready_backend(): void {
		$action_scheduler = $this->recording_backend( ready: false );
		$wp_cron          = $this->recording_backend();
		$scheduler        = new Scheduler( array( $action_scheduler, $wp_cron ) );

		$result = $scheduler->schedule_single( 'dws_hook', 1700000000 );

		self::assertSame(
			array( array( 'schedule_single', 'dws_hook', 1700000000, array(), '' ) ),
			$wp_cron->calls,
		);
		self::assertSame( array(), $action_scheduler->calls );
		self::assertSame( $wp_cron->next_result, $result );
	}

	public function test_schedule_write_falls_back_to_the_last_backend_when_none_is_ready(): void {
		$action_scheduler = $this->recording_backend( ready: false );
		$wp_cron          = $this->recording_backend( ready: false );
		$scheduler        = new Scheduler( array( $action_scheduler, $wp_cron ) );

		$result = $scheduler->schedule_single( 'dws_hook', 1700000000 );

		self::assertSame(
			array( array( 'schedule_single', 'dws_hook', 1700000000, array(), '' ) ),
			$wp_cron->calls,
		);
		self::assertSame( array(), $action_scheduler->calls );
		self::assertSame( $wp_cron->next_result, $result );
	}

	public function test_string_keyed_backend_maps_are_reindexed_so_the_last_backend_fallback_holds(): void {
		$action_scheduler = $this->recording_backend( ready: false );
		$wp_cron          = $this->recording_backend( ready: false );
		$scheduler        = new Scheduler(
			array(
				'action-scheduler' => $action_scheduler,
				'wp-cron'          => $wp_cron,
			),
		);

		$result = $scheduler->schedule_single( 'dws_hook', 1700000000 );

		self::assertSame(
			array( array( 'schedule_single', 'dws_hook', 1700000000, array(), '' ) ),
			$wp_cron->calls,
		);
		self::assertSame( array(), $action_scheduler->calls );
		self::assertSame( $wp_cron->next_result, $result );
	}

	public function test_unschedule_clears_jobs_from_both_backends(): void {
		$action_scheduler = $this->recording_backend( true, 1700000000 );
		$wp_cron          = $this->recording_backend( true, 1700000100 );
		$scheduler        = new Scheduler( array( $action_scheduler, $wp_cron ) );

		$result = $scheduler->unschedule( 'dws_hook', array( 'x' ), 'grp' );

		self::assertInstanceOf( Success::class, $result );
		self::assertTrue( $result->value );
		self::assertFalse( $action_scheduler->is_scheduled( 'dws_hook', array( 'x' ), 'grp' ) );
		self::assertFalse( $wp_cron->is_scheduled( 'dws_hook', array( 'x' ), 'grp' ) );
	}

	public function test_unschedule_returns_failure_when_either_backend_fails(): void {
		$failure          = Failure::from( new SchedulingError( SchedulingErrorReason::ScheduleFailed, 'No clear.' ) );
		$action_scheduler = $this->recording_backend();
		$wp_cron          = $this->recording_backend( next_result: $failure );
		$scheduler        = new Scheduler( array( $action_scheduler, $wp_cron ) );

		self::assertSame( $failure, $scheduler->unschedule( 'dws_hook' ) );
	}

	public function test_unschedule_returns_the_first_backend_failure_when_only_it_fails(): void {
		$failure          = Failure::from( new SchedulingError( SchedulingErrorReason::ScheduleFailed, 'No clear.' ) );
		$action_scheduler = $this->recording_backend( next_result: $failure );
		$wp_cron          = $this->recording_backend();
		$scheduler        = new Scheduler( array( $action_scheduler, $wp_cron ) );

		self::assertSame( $failure, $scheduler->unschedule( 'dws_hook' ) );
		self::assertSame(
			array( array( 'unschedule', 'dws_hook', array(), '' ) ),
			$action_scheduler->calls,
		);
		self::assertSame(
			array( array( 'unschedule', 'dws_hook', array(), '' ) ),
			$wp_cron->calls,
		);
	}

	public function test_unschedule_failure_precedence_follows_declaration_order(): void {
		$action_scheduler_failure = Failure::from( new SchedulingError( SchedulingErrorReason::ScheduleFailed, 'AS did not clear.' ) );
		$wp_cron_failure          = Failure::from( new SchedulingError( SchedulingErrorReason::ScheduleFailed, 'WP-Cron did not clear.' ) );
		$action_scheduler         = $this->recording_backend( next_result: $action_scheduler_failure );
		$wp_cron                  = $this->recording_backend( next_result: $wp_cron_failure );
		$scheduler                = new Scheduler( array( $action_scheduler, $wp_cron ) );

		self::assertSame( $action_scheduler_failure, $scheduler->unschedule( 'dws_hook' ) );
	}

	public function test_unschedule_skips_a_not_ready_backend_and_clears_the_ready_one(): void {
		$failure          = Failure::from( new SchedulingError( SchedulingErrorReason::ActionSchedulerNotLoaded, 'AS unavailable.' ) );
		$action_scheduler = $this->recording_backend( next_result: $failure, ready: false );
		$wp_cron          = $this->recording_backend( true, 1700000000 );
		$scheduler        = new Scheduler( array( $action_scheduler, $wp_cron ) );

		$result = $scheduler->unschedule( 'dws_hook', array( 'x' ) );

		self::assertInstanceOf( Success::class, $result );
		self::assertSame( array(), $action_scheduler->calls );
		self::assertSame(
			array( array( 'unschedule', 'dws_hook', array( 'x' ), '' ) ),
			$wp_cron->calls,
		);
		self::assertFalse( $wp_cron->scheduled );
	}

	public function test_unschedule_with_group_skips_a_not_ready_backend(): void {
		$failure          = Failure::from( new SchedulingError( SchedulingErrorReason::ActionSchedulerNotLoaded, 'AS unavailable.' ) );
		$action_scheduler = $this->recording_backend( next_result: $failure, ready: false );
		$wp_cron          = $this->recording_backend();
		$scheduler        = new Scheduler( array( $action_scheduler, $wp_cron ) );

		$result = $scheduler->unschedule( 'dws_hook', array( 'x' ), 'reports' );

		self::assertInstanceOf( Success::class, $result );
		self::assertSame( array(), $action_scheduler->calls );
		self::assertSame(
			array( array( 'unschedule', 'dws_hook', array( 'x' ), 'reports' ) ),
			$wp_cron->calls,
		);
	}

	public function test_unschedule_is_a_vacuous_success_when_no_backend_is_ready(): void {
		$action_scheduler = $this->recording_backend( ready: false );
		$wp_cron          = $this->recording_backend( ready: false );
		$scheduler        = new Scheduler( array( $action_scheduler, $wp_cron ) );

		self::assertInstanceOf( Success::class, $scheduler->unschedule( 'dws_hook' ) );
		self::assertSame( array(), $action_scheduler->calls );
		self::assertSame( array(), $wp_cron->calls );
	}

	public function test_is_scheduled_finds_a_job_from_either_backend(): void {
		$action_scheduler = $this->recording_backend( false );
		$wp_cron          = $this->recording_backend( true );
		$scheduler        = new Scheduler( array( $action_scheduler, $wp_cron ) );

		self::assertTrue( $scheduler->is_scheduled( 'dws_hook' ) );
	}

	public function test_is_scheduled_finds_a_job_present_only_in_the_first_backend(): void {
		$action_scheduler = $this->recording_backend( true );
		$wp_cron          = $this->recording_backend( false );
		$scheduler        = new Scheduler( array( $action_scheduler, $wp_cron ) );

		self::assertTrue( $scheduler->is_scheduled( 'dws_hook' ) );
	}

	public function test_is_scheduled_returns_false_when_neither_backend_has_the_job(): void {
		$action_scheduler = $this->recording_backend( false );
		$wp_cron          = $this->recording_backend( false );
		$scheduler        = new Scheduler( array( $action_scheduler, $wp_cron ) );

		self::assertFalse( $scheduler->is_scheduled( 'dws_hook' ) );
	}

	public function test_is_scheduled_skips_a_not_ready_backend(): void {
		$action_scheduler = $this->recording_backend( true, ready: false );
		$wp_cron          = $this->recording_backend( false );
		$scheduler        = new Scheduler( array( $action_scheduler, $wp_cron ) );

		self::assertFalse( $scheduler->is_scheduled( 'dws_hook', array( 'x' ), 'reports' ) );
		self::assertSame( array(), $action_scheduler->calls );
		self::assertSame(
			array( array( 'is_scheduled', 'dws_hook', array( 'x' ), 'reports' ) ),
			$wp_cron->calls,
		);
	}

	public function test_is_scheduled_short_circuits_on_the_first_ready_hit(): void {
		$action_scheduler = $this->recording_backend( true );
		$wp_cron          = $this->recording_backend( false );
		$scheduler        = new Scheduler( array( $action_scheduler, $wp_cron ) );

		self::assertTrue( $scheduler->is_scheduled( 'dws_hook', array( 'x' ), 'reports' ) );
		self::assertSame(
			array( array( 'is_scheduled', 'dws_hook', array( 'x' ), 'reports' ) ),
			$action_scheduler->calls,
		);
		self::assertSame( array(), $wp_cron->calls );
	}

	public function test_get_next_scheduled_returns_the_earliest_timestamp_across_backends(): void {
		$action_scheduler = $this->recording_backend( false, 1700000300 );
		$wp_cron          = $this->recording_backend( false, 1700000100 );
		$scheduler        = new Scheduler( array( $action_scheduler, $wp_cron ) );

		self::assertSame( 1700000100, $scheduler->get_next_scheduled( 'dws_hook', array( 'x' ), 'grp' ) );
	}

	public function test_get_next_scheduled_returns_the_only_scheduled_timestamp(): void {
		$action_scheduler = $this->recording_backend( false, 1700000000 );
		$wp_cron          = $this->recording_backend( false, null );
		$scheduler        = new Scheduler( array( $action_scheduler, $wp_cron ) );

		self::assertSame( 1700000000, $scheduler->get_next_scheduled( 'dws_hook', array( 'x' ), 'grp' ) );
	}

	public function test_get_next_scheduled_returns_null_when_neither_backend_has_the_job(): void {
		$action_scheduler = $this->recording_backend( false, null );
		$wp_cron          = $this->recording_backend( false, null );
		$scheduler        = new Scheduler( array( $action_scheduler, $wp_cron ) );

		self::assertNull( $scheduler->get_next_scheduled( 'dws_hook' ) );
	}

	public function test_get_next_scheduled_skips_a_not_ready_backend(): void {
		$action_scheduler = $this->recording_backend( false, 1700000000, ready: false );
		$wp_cron          = $this->recording_backend( false, null );
		$scheduler        = new Scheduler( array( $action_scheduler, $wp_cron ) );

		self::assertNull( $scheduler->get_next_scheduled( 'dws_hook', array( 'x' ), 'reports' ) );
		self::assertSame( array(), $action_scheduler->calls );
		self::assertSame(
			array( array( 'get_next_scheduled', 'dws_hook', array( 'x' ), 'reports' ) ),
			$wp_cron->calls,
		);
	}

	public function test_get_next_scheduled_consults_every_ready_backend(): void {
		$action_scheduler = $this->recording_backend( false, 1700000000 );
		$wp_cron          = $this->recording_backend( false, null );
		$scheduler        = new Scheduler( array( $action_scheduler, $wp_cron ) );

		self::assertSame( 1700000000, $scheduler->get_next_scheduled( 'dws_hook', array( 'x' ), 'reports' ) );
		self::assertSame(
			array( array( 'get_next_scheduled', 'dws_hook', array( 'x' ), 'reports' ) ),
			$action_scheduler->calls,
		);
		self::assertSame(
			array( array( 'get_next_scheduled', 'dws_hook', array( 'x' ), 'reports' ) ),
			$wp_cron->calls,
		);
	}

	public function test_is_ready_reports_whether_any_backend_is_ready(): void {
		$ready     = $this->recording_backend();
		$dormant_a = $this->recording_backend( ready: false );
		$dormant_b = $this->recording_backend( ready: false );

		self::assertTrue( ( new Scheduler( array( $dormant_a, $ready ) ) )->is_ready() );
		self::assertFalse( ( new Scheduler( array( $dormant_a, $dormant_b ) ) )->is_ready() );
	}

	public function test_register_hooks_wires_every_backend_ready_or_not(): void {
		$action_scheduler = $this->recording_backend( ready: false );
		$wp_cron          = $this->recording_backend();
		$scheduler        = new Scheduler( array( $action_scheduler, $wp_cron ) );

		$scheduler->register_hooks();

		self::assertSame( 1, $action_scheduler->lifecycle_calls );
		self::assertSame( 1, $wp_cron->lifecycle_calls );
	}

	public function test_write_backend_is_selected_per_call_as_readiness_changes(): void {
		$action_scheduler = $this->recording_backend( ready: false );
		$wp_cron          = $this->recording_backend();
		$scheduler        = new Scheduler( array( $action_scheduler, $wp_cron ) );

		(void) $scheduler->schedule_single( 'first', 1700000000 );
		$action_scheduler->ready = true;
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
	 * @param   bool                                          $scheduled   Whether the fake initially has the job.
	 * @param   int|null                                      $next_int    Value the fake returns from get_next_scheduled().
	 * @param   Success<true>|Failure<SchedulingError>|null   $next_result Result the fake returns from mutations; null means success.
	 * @param   bool                                          $ready       Whether the fake reports itself ready.
	 *
	 * @return  SchedulerTestRecordingBackend
	 */
	protected function recording_backend( bool $scheduled = false, ?int $next_int = null, Success|Failure|null $next_result = null, bool $ready = true ): SchedulerTestRecordingBackend {
		return new SchedulerTestRecordingBackend( $scheduled, $next_int, $next_result, $ready );
	}
}

/**
 * Recording scheduler-backend fake with controllable readiness, queried state, and mutation result.
 */
final class SchedulerTestRecordingBackend implements SchedulerBackendInterface {
	/** @var list<array<int, mixed>> */
	public array $calls = array();

	public int $lifecycle_calls = 0;

	public bool $scheduled;

	public ?int $next_int;

	/** @var Success<true>|Failure<SchedulingError> */
	public readonly Success|Failure $next_result;

	public bool $ready;

	/**
	 * Constructor.
	 *
	 * @param   bool                                        $scheduled   Whether the fake initially has the job.
	 * @param   int|null                                    $next_int    Value the fake returns from get_next_scheduled().
	 * @param   Success<true>|Failure<SchedulingError>|null $next_result Result the fake returns from mutations; null means success.
	 * @param   bool                                        $ready       Whether the fake reports itself ready.
	 */
	public function __construct(
		bool $scheduled,
		?int $next_int,
		Success|Failure|null $next_result,
		bool $ready,
	) {
		$this->scheduled   = $scheduled;
		$this->next_int    = $next_int;
		$this->next_result = $next_result ?? Success::from( true );
		$this->ready       = $ready;
	}

	public function schedule_recurring( string $hook, int $interval, array $args = array(), ?int $first_run_timestamp = null, string $group = '' ): Success|Failure {
		$this->calls[] = array( 'schedule_recurring', $hook, $interval, $args, $first_run_timestamp, $group );
		return $this->next_result;
	}

	public function schedule_single( string $hook, int $timestamp, array $args = array(), string $group = '' ): Success|Failure {
		$this->calls[] = array( 'schedule_single', $hook, $timestamp, $args, $group );
		return $this->next_result;
	}

	public function unschedule( string $hook, array $args = array(), string $group = '' ): Success|Failure {
		$this->calls[] = array( 'unschedule', $hook, $args, $group );
		if ( $this->next_result->is_success() ) {
			$this->scheduled = false;
			$this->next_int  = null;
		}
		return $this->next_result;
	}

	public function is_scheduled( string $hook, array $args = array(), string $group = '' ): bool {
		$this->calls[] = array( 'is_scheduled', $hook, $args, $group );
		return $this->scheduled;
	}

	public function get_next_scheduled( string $hook, array $args = array(), string $group = '' ): ?int {
		$this->calls[] = array( 'get_next_scheduled', $hook, $args, $group );
		return $this->next_int;
	}

	public function is_ready(): bool {
		return $this->ready;
	}

	public function register_hooks(): void {
		++$this->lifecycle_calls;
	}
}
