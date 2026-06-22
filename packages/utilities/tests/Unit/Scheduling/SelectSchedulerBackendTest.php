<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Unit\Scheduling;

use DeepWebSolutions\Framework\Utilities\Scheduling\Backends\ActionSchedulerBackend;
use DeepWebSolutions\Framework\Utilities\Scheduling\Backends\WPCronBackend;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\TestCase;

use function DeepWebSolutions\Framework\Utilities\Scheduling\select_scheduler_backend;

#[CoversFunction( 'DeepWebSolutions\Framework\Utilities\Scheduling\select_scheduler_backend' )]
final class SelectSchedulerBackendTest extends TestCase {
	public function test_selects_action_scheduler_when_probe_reports_available(): void {
		$backend = select_scheduler_backend( null, static fn (): bool => true );

		self::assertInstanceOf( ActionSchedulerBackend::class, $backend );
	}

	public function test_selects_wp_cron_when_probe_reports_unavailable(): void {
		$backend = select_scheduler_backend( null, static fn (): bool => false );

		self::assertInstanceOf( WPCronBackend::class, $backend );
	}
}
