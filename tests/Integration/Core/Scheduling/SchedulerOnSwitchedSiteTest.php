<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Core\Scheduling;

use DeepWebSolutions\Framework\Scheduling\Recurrence;
use DeepWebSolutions\Framework\Scheduling\ScheduledHook;
use DeepWebSolutions\Framework\Scheduling\Scheduler;
use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesSitesTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass( Scheduler::class )]
#[Group( 'multisite' )]
final class SchedulerOnSwitchedSiteTest extends TestCase {
	use CreatesSitesTrait;
	use IsolatesHooksTrait;

	protected string $hook;

	protected int $other_site;

	protected array $doing_it_wrong = array();

	protected array $writes = array();

	#[\Override]
	protected function setUp(): void {
		$this->hook       = 'acme_scheduling_' . \bin2hex( \random_bytes( 4 ) );
		$this->other_site = $this->make_site( 'acme-scheduling' );
		\add_filter( 'doing_it_wrong_trigger_error', '__return_false' );
		\add_action(
			'doing_it_wrong_run',
			function ( string $function_name ): void {
				$this->doing_it_wrong[] = $function_name;
			}
		);
		\add_filter(
			'pre_update_option_cron',
			function ( mixed $value, mixed $old_value ): mixed {
				if ( $value !== $old_value ) {
					$this->writes[] = \get_current_blog_id();
				}

				return $value;
			},
			10,
			2
		);
	}

	#[\Override]
	protected function tearDown(): void {
		while ( \ms_is_switched() ) {
			\restore_current_blog();
		}

		\wp_unschedule_hook( $this->hook );
		\switch_to_blog( $this->other_site );
		\wp_unschedule_hook( $this->hook );
		\restore_current_blog();
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
	public function test_a_write_on_a_switched_site_is_an_error_that_writes_nothing( \Closure $write ): void {
		$scheduler = $this->make_scheduler();
		\switch_to_blog( $this->other_site );
		\wp_schedule_single_event( \time() + 60, $this->hook );
		$this->writes = array();

		$result = $write( $scheduler, new ScheduledHook( $this->hook ) );
		\restore_current_blog();

		self::assertInstanceOf( \WP_Error::class, $result );
		self::assertSame( 'scheduler_switched', $result->get_error_code() );
		self::assertCount( 1, $this->doing_it_wrong );
		self::assertSame( array(), $this->writes );
	}

	public function test_is_scheduled_on_a_switched_site_is_false_with_a_developer_notice(): void {
		$scheduler = $this->make_scheduler();
		$hook      = new ScheduledHook( $this->hook );
		\switch_to_blog( $this->other_site );
		\wp_schedule_single_event( \time() + 60, $this->hook );

		$scheduled = $scheduler->is_scheduled( $hook );
		\restore_current_blog();

		self::assertFalse( $scheduled );
		self::assertCount( 1, $this->doing_it_wrong );
	}

	public function test_the_site_that_loaded_the_plugin_schedules_after_a_switch_is_restored(): void {
		$scheduler = $this->make_scheduler();
		$hook      = new ScheduledHook( $this->hook );
		\switch_to_blog( $this->other_site );
		\restore_current_blog();

		self::assertTrue( $scheduler->enqueue( $hook ) );

		self::assertTrue( $scheduler->is_scheduled( $hook ) );
		self::assertSame( array( \get_main_site_id() ), $this->writes );
		self::assertSame( array(), $this->doing_it_wrong );
	}

	// The network carries no WooCommerce, so the default backends resolve to WP-Cron alone.
	protected function make_scheduler(): Scheduler {
		$scheduler = new Scheduler( 'acme' );
		$scheduler->register_hooks();

		return $scheduler;
	}
}
