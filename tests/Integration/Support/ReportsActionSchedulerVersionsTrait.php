<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Support;

use PHPUnit\Framework\Attributes\After;

// The loaded Action Scheduler is at the floor, so a test below it changes only the versions it reports.
trait ReportsActionSchedulerVersionsTrait {
	protected ?array $registered_versions = null;

	protected function report_action_scheduler_versions( array $versions ): void {
		$property                    = new \ReflectionProperty( \ActionScheduler_Versions::class, 'versions' );
		$this->registered_versions ??= $property->getValue( \ActionScheduler_Versions::instance() );
		$property->setValue( \ActionScheduler_Versions::instance(), \array_fill_keys( $versions, '__return_null' ) );
	}

	#[After]
	protected function restore_action_scheduler_versions(): void {
		if ( ! \is_null( $this->registered_versions ) ) {
			( new \ReflectionProperty( \ActionScheduler_Versions::class, 'versions' ) )->setValue( \ActionScheduler_Versions::instance(), $this->registered_versions );
			$this->registered_versions = null;
		}
	}
}
