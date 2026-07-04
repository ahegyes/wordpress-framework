<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Scheduling;

use DeepWebSolutions\Framework\Utilities\Scheduling\Backends\ActionSchedulerBackend;
use DeepWebSolutions\Framework\Utilities\Scheduling\Backends\WPCronBackend;
use Psr\Log\LoggerInterface;

/**
 * Builds a {@see Scheduler} wired to the default backend order: Action Scheduler, then WordPress cron.
 *
 * The returned scheduler targets schedule writes at Action Scheduler when it reports itself ready and
 * at WordPress cron otherwise; its read and clear surface spans every ready backend. The probe is
 * injectable so the readiness branch is testable without driving the Action Scheduler runtime.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   LoggerInterface|null $logger                       Optional PSR-3 logger passed to both backends.
 * @param   callable|null        $action_scheduler_ready_probe Predicate reporting whether Action Scheduler is ready; defaults to {@see action_scheduler_is_ready()}.
 *
 * @return  Scheduler
 */
function create_scheduler( ?LoggerInterface $logger = null, ?callable $action_scheduler_ready_probe = null ): Scheduler {
	return new Scheduler(
		array(
			new ActionSchedulerBackend( $logger, $action_scheduler_ready_probe ),
			new WPCronBackend( $logger ),
		),
	);
}

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
