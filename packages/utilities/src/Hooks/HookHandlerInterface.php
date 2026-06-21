<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Hooks;

/**
 * Strategy for registering WordPress hook callbacks.
 *
 * Implementations differ in how they bind callbacks to WordPress — direct passthrough
 * to add_action() / add_filter(), buffered (queued) registration, scoped (gated by start
 * and end WordPress hooks), etc. The HooksService composes one or more handlers and
 * dispatches by handler ID.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
interface HookHandlerInterface {
	/**
	 * Returns the unique identifier of this handler instance, used by the service to
	 * route registration calls.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  string
	 */
	public function get_id(): string;

	/**
	 * Register a callback for a WordPress action.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string   $hook           Action hook name.
	 * @param   callable $callback       Callback to register.
	 * @param   int      $priority       Hook priority (lower runs earlier).
	 * @param   int      $accepted_args  Number of arguments the callback accepts.
	 */
	public function add_action( string $hook, callable $callback, int $priority, int $accepted_args ): void;

	/**
	 * Register a callback for a WordPress filter.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string   $hook           Filter hook name.
	 * @param   callable $callback       Callback to register.
	 * @param   int      $priority       Hook priority (lower runs earlier).
	 * @param   int      $accepted_args  Number of arguments the callback accepts.
	 */
	public function add_filter( string $hook, callable $callback, int $priority, int $accepted_args ): void;

	/**
	 * Remove a previously registered action callback.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string   $hook      Action hook name.
	 * @param   callable $callback  Callback originally registered.
	 * @param   int      $priority  Hook priority used at registration.
	 *
	 * @return  bool
	 */
	public function remove_action( string $hook, callable $callback, int $priority ): bool;

	/**
	 * Remove a previously registered filter callback.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string   $hook      Filter hook name.
	 * @param   callable $callback  Callback originally registered.
	 * @param   int      $priority  Hook priority used at registration.
	 *
	 * @return  bool
	 */
	public function remove_filter( string $hook, callable $callback, int $priority ): bool;

	/**
	 * Remove every action that this handler registered.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function remove_all_actions(): void;

	/**
	 * Remove every filter that this handler registered.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function remove_all_filters(): void;
}
