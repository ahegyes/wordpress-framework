<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Scheduling;

/**
 * Machine-readable reason a scheduling mutation failed.
 *
 * Carried by {@see SchedulingError} so a caller can branch on the cause without
 * parsing the human message. The closed set covers every failure a backend can
 * report: Action Scheduler absent, a grouping request a backend cannot honor,
 * a non-positive interval, and a backend rejecting the schedule outright.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
enum SchedulingErrorReason: string {
	case ActionSchedulerNotLoaded = 'action_scheduler_not_loaded';
	case UnsupportedGroup         = 'unsupported_group';
	case InvalidInterval          = 'invalid_interval';
	case ScheduleFailed           = 'schedule_failed';
}
