<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Unit\Scheduling\Backends;

use DeepWebSolutions\Framework\Shared\Result\Failure;
use DeepWebSolutions\Framework\Shared\Result\Success;
use DeepWebSolutions\Framework\Utilities\Scheduling\Backends\WPCronBackend;
use DeepWebSolutions\Framework\Utilities\Scheduling\Errors\SchedulingError;
use DeepWebSolutions\Framework\Utilities\Scheduling\SchedulingErrorReason;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( WPCronBackend::class )]
#[UsesClass( Success::class )]
#[UsesClass( Failure::class )]
#[UsesClass( SchedulingError::class )]
#[UsesClass( SchedulingErrorReason::class )]
final class WPCronBackendTest extends TestCase {
	public function test_is_ready_is_always_true(): void {
		self::assertTrue( ( new WPCronBackend() )->is_ready() );
	}

	public function test_unschedule_with_non_empty_group_is_a_success_noop_without_calling_wp_cron(): void {
		self::assertFalse( \function_exists( 'wp_clear_scheduled_hook' ), 'WP must not be loaded for this unit guard.' );

		$result = ( new WPCronBackend() )->unschedule( 'dws_hook', array( 'x' ), 'reports' );

		self::assertInstanceOf( Success::class, $result );
		self::assertTrue( $result->value );
	}

	public function test_schedule_recurring_still_rejects_a_non_empty_group(): void {
		self::assertFalse( \function_exists( 'wp_schedule_event' ), 'WP must not be loaded for this unit guard.' );

		$result = ( new WPCronBackend() )->schedule_recurring( 'dws_hook', 300, array(), null, 'reports' );

		self::assertInstanceOf( Failure::class, $result );
		self::assertInstanceOf( SchedulingError::class, $result->error );
		self::assertSame( SchedulingErrorReason::UnsupportedGroup, $result->error->reason );
	}

	public function test_schedule_single_still_rejects_a_non_empty_group(): void {
		self::assertFalse( \function_exists( 'wp_schedule_single_event' ), 'WP must not be loaded for this unit guard.' );

		$result = ( new WPCronBackend() )->schedule_single( 'dws_hook', 1700000000, array(), 'reports' );

		self::assertInstanceOf( Failure::class, $result );
		self::assertInstanceOf( SchedulingError::class, $result->error );
		self::assertSame( SchedulingErrorReason::UnsupportedGroup, $result->error->reason );
	}
}
