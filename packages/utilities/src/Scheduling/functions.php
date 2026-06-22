<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Scheduling;

use DeepWebSolutions\Framework\Utilities\Scheduling\Backends\ActionSchedulerBackend;
use DeepWebSolutions\Framework\Utilities\Scheduling\Backends\WPCronBackend;
use Psr\Log\LoggerInterface;

/**
 * Selects the scheduler backend, preferring Action Scheduler when it is loaded.
 *
 * Returns an {@see ActionSchedulerBackend} when Action Scheduler's scheduling API is
 * available, otherwise a {@see WPCronBackend}. The availability probe is injectable so
 * both branches are testable without manipulating the global function table.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   LoggerInterface|null $logger                  Optional PSR-3 logger passed to the chosen backend.
 * @param   callable|null        $action_scheduler_probe  Predicate reporting whether Action Scheduler is available; defaults to a function_exists check.
 *
 * @return  SchedulerBackendInterface
 */
function select_scheduler_backend( ?LoggerInterface $logger = null, ?callable $action_scheduler_probe = null ): SchedulerBackendInterface {
	$probe = $action_scheduler_probe ?? static fn (): bool => \function_exists( 'as_schedule_recurring_action' );

	return $probe()
		? new ActionSchedulerBackend( $logger )
		: new WPCronBackend( $logger );
}
