<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Support;

trait DispatchesScheduledHooksTrait {
	// Mirrors one Action Scheduler queue-runner batch, which claims the hook's actions due by the given time or now, and returns how many it ran.
	protected static function run_due_actions( string $hook, ?int $at = null ): int {
		$store = \ActionScheduler::store();
		$claim = $store->stake_claim( 10, \is_null( $at ) ? null : new \DateTime( "@$at" ), array( $hook ) );
		foreach ( $claim->get_actions() as $action_id ) {
			\ActionScheduler::runner()->process_action( (int) $action_id, 'Tests' );
		}

		$store->release_claim( $claim );

		return \count( $claim->get_actions() );
	}

	// Mirrors one wp-cron.php run, which reschedules and runs the hook's events due by the given time or now, and returns each reschedule's result.
	protected static function run_due_cron_events( string $hook, ?int $at = null ): array {
		$rescheduled = array();
		foreach ( _get_cron_array() as $timestamp => $hooks ) {
			if ( $timestamp > ( $at ?? \time() ) ) {
				break;
			}

			foreach ( $hooks[ $hook ] ?? array() as $event ) {
				if ( false !== $event['schedule'] ) {
					$rescheduled[] = wp_reschedule_event( $timestamp, $event['schedule'], $hook, $event['args'], true );
				}

				wp_unschedule_event( $timestamp, $hook, $event['args'], true );
				do_action_ref_array( $hook, $event['args'] );
			}
		}

		return $rescheduled;
	}
}
