<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Scheduling;

/**
 * Whether Action Scheduler is loaded and its data store has finished initializing.
 *
 * Action Scheduler's procedural API returns no-op values until 'action_scheduler_init' fires, which is
 * later than the 'plugins_loaded' wiring, so readiness needs both the function table and the init
 * signal. The two probes are injectable so every row of the readiness table is unit-testable without
 * driving the Action Scheduler runtime.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   callable(string): bool $function_exists_probe Reports whether a named function exists; defaults to function_exists().
 * @param   callable(string): int  $did_action_probe      Reports how many times an action has fired; defaults to did_action().
 *
 * @return  bool
 */
function action_scheduler_is_ready( ?callable $function_exists_probe = null, ?callable $did_action_probe = null ): bool {
	$function_exists = $function_exists_probe ?? static fn ( string $name ): bool => \function_exists( $name );
	$did_action      = $did_action_probe ?? static fn ( string $hook ): int => \did_action( $hook );

	return $function_exists( 'as_schedule_recurring_action' ) && $did_action( 'action_scheduler_init' ) > 0;
}
