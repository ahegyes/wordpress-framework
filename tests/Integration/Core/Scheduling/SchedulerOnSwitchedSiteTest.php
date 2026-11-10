<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Core\Scheduling;

use DeepWebSolutions\Framework\Core\Scheduling\ScheduledHook;
use DeepWebSolutions\Framework\Core\Scheduling\Scheduler;
use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesSitesTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\RecordsDoingItWrongTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass( Scheduler::class )]
#[Group( 'multisite' )]
final class SchedulerOnSwitchedSiteTest extends TestCase {
	use CreatesSitesTrait;
	use IsolatesHooksTrait;
	use RecordsDoingItWrongTrait;

	protected string $hook;

	protected int $other_site;

	protected array $writes = array();

	#[\Override]
	protected function setUp(): void {
		$this->hook       = 'acme_scheduling_' . \bin2hex( \random_bytes( 4 ) );
		$this->other_site = $this->make_site( 'acme-scheduling' );
		$this->record_doing_it_wrong();
		add_filter(
			'pre_update_option_cron',
			function ( mixed $value, mixed $old_value ): mixed {
				if ( $value !== $old_value ) {
					$this->writes[] = get_current_blog_id();
				}

				return $value;
			},
			10,
			2
		);
	}

	#[\Override]
	protected function tearDown(): void {
		// The cleanup starts on the main site, and the trait restores the sites only after tearDown().
		$this->restore_switched_sites();
		wp_unschedule_hook( $this->hook );
		switch_to_blog( $this->other_site );
		wp_unschedule_hook( $this->hook );
		restore_current_blog();
	}

	#[DataProviderExternal( SchedulerTest::class, 'writes' )]
	public function test_a_write_on_a_switched_site_is_an_error_that_writes_nothing( \Closure $write ): void {
		$scheduler = $this->make_scheduler();
		switch_to_blog( $this->other_site );
		wp_schedule_single_event( \time() + 60, $this->hook );
		$this->writes = array();

		$result = $write( $scheduler, new ScheduledHook( $this->hook ) );
		restore_current_blog();

		self::assertInstanceOf( \WP_Error::class, $result );
		self::assertSame( 'scheduler_switched', $result->get_error_code() );
		self::assertCount( 1, $this->doing_it_wrong );
		self::assertSame( array(), $this->writes );
	}

	public function test_is_scheduled_on_a_switched_site_is_false_with_a_developer_notice(): void {
		$scheduler = $this->make_scheduler();
		$hook      = new ScheduledHook( $this->hook );
		switch_to_blog( $this->other_site );
		wp_schedule_single_event( \time() + 60, $this->hook );

		$scheduled = $scheduler->is_scheduled( $hook );
		restore_current_blog();

		self::assertFalse( $scheduled );
		self::assertCount( 1, $this->doing_it_wrong );
	}

	public function test_the_site_that_loaded_the_plugin_schedules_after_a_switch_is_restored(): void {
		$scheduler = $this->make_scheduler();
		$hook      = new ScheduledHook( $this->hook );
		switch_to_blog( $this->other_site );
		restore_current_blog();

		self::assertTrue( $scheduler->enqueue( $hook ) );

		self::assertTrue( $scheduler->is_scheduled( $hook ) );
		self::assertSame( array( get_main_site_id() ), $this->writes );
		self::assertSame( array(), $this->doing_it_wrong );
	}

	public function test_a_switch_to_the_site_that_loaded_the_plugin_still_schedules(): void {
		$scheduler = $this->make_scheduler();
		$hook      = new ScheduledHook( $this->hook );
		switch_to_blog( get_current_blog_id() );

		$enqueued = $scheduler->enqueue( $hook );
		restore_current_blog();

		self::assertTrue( $enqueued );
		self::assertSame( array( get_main_site_id() ), $this->writes );
		self::assertSame( array(), $this->doing_it_wrong );
	}

	// The network carries no WooCommerce, so the default backends resolve to WP-Cron alone.
	protected function make_scheduler(): Scheduler {
		$scheduler = new Scheduler( 'acme' );
		$scheduler->register_hooks();

		return $scheduler;
	}
}
