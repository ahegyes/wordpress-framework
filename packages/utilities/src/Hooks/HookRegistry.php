<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Hooks;

/**
 * Internal data store shared by hook handlers.
 *
 * Keeps two parallel lists (actions, filters) of registration records. Handlers
 * compose this class to track what they've added so they can replay registration
 * (BufferedHookHandler) or perform an exhaustive un-register (DirectHookHandler::remove_all_*).
 *
 * Records carry the hook name, callback, priority, and accepted args — everything
 * needed to call WordPress's add_action / remove_action with matching arguments.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class HookRegistry {
	// region FIELDS AND CONSTANTS

	/**
	 * Action registration records.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     list<array{hook: string, callback: callable, priority: int, accepted_args: int}>
	 */
	private array $actions = array();

	/**
	 * Filter registration records.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     list<array{hook: string, callback: callable, priority: int, accepted_args: int}>
	 */
	private array $filters = array();

	// endregion

	// region METHODS

	/**
	 * Append an action record.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string   $hook           Action hook name.
	 * @param   callable $callback       Callback registered.
	 * @param   int      $priority       Hook priority.
	 * @param   int      $accepted_args  Number of arguments the callback accepts.
	 */
	public function record_action( string $hook, callable $callback, int $priority, int $accepted_args ): void {
		$this->actions[] = array(
			'hook'          => $hook,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
	}

	/**
	 * Append a filter record.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string   $hook           Filter hook name.
	 * @param   callable $callback       Callback registered.
	 * @param   int      $priority       Hook priority.
	 * @param   int      $accepted_args  Number of arguments the callback accepts.
	 */
	public function record_filter( string $hook, callable $callback, int $priority, int $accepted_args ): void {
		$this->filters[] = array(
			'hook'          => $hook,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
	}

	/**
	 * Remove the first action record matching hook, callback, and priority.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string   $hook      Action hook name.
	 * @param   callable $callback  Callback originally registered.
	 * @param   int      $priority  Hook priority used at registration.
	 *
	 * @return  bool                True if a record was found and removed.
	 */
	public function forget_action( string $hook, callable $callback, int $priority ): bool {
		return $this->forget_from( $this->actions, $hook, $callback, $priority );
	}

	/**
	 * Remove the first filter record matching hook, callback, and priority.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string   $hook      Filter hook name.
	 * @param   callable $callback  Callback originally registered.
	 * @param   int      $priority  Hook priority used at registration.
	 *
	 * @return  bool                True if a record was found and removed.
	 */
	public function forget_filter( string $hook, callable $callback, int $priority ): bool {
		return $this->forget_from( $this->filters, $hook, $callback, $priority );
	}

	/**
	 * Drop every recorded action.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function clear_actions(): void {
		$this->actions = array();
	}

	/**
	 * Drop every recorded filter.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function clear_filters(): void {
		$this->filters = array();
	}

	// endregion

	// region GETTERS

	/**
	 * Return all recorded action registrations.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  list<array{hook: string, callback: callable, priority: int, accepted_args: int}>
	 */
	public function get_actions(): array {
		return $this->actions;
	}

	/**
	 * Return all recorded filter registrations.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  list<array{hook: string, callback: callable, priority: int, accepted_args: int}>
	 */
	public function get_filters(): array {
		return $this->filters;
	}

	// endregion

	// region HELPERS

	/**
	 * Find and remove the first matching record from a record list, mutating in place.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   list<array{hook: string, callback: callable, priority: int, accepted_args: int}> $records  Record list (passed by reference).
	 * @param   string                                                                           $hook     Hook name to match.
	 * @param   callable                                                                         $callback Callback to match.
	 * @param   int                                                                              $priority Priority to match.
	 *
	 * @return  bool                                                                                        True if a record was removed.
	 */
	private function forget_from( array &$records, string $hook, callable $callback, int $priority ): bool {
		foreach ( $records as $index => $record ) {
			if ( $record['hook'] === $hook && $record['callback'] === $callback && $record['priority'] === $priority ) {
				array_splice( $records, $index, 1 );
				return true;
			}
		}
		return false;
	}

	// endregion
}
