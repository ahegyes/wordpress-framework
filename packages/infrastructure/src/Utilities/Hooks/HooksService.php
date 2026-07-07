<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Hooks;

use DeepWebSolutions\Framework\Utilities\Hooks\Exceptions\UnknownHookHandlerException;
use DeepWebSolutions\Framework\Utilities\Hooks\Handlers\DirectHookHandler;

/**
 * Multi-handler hook registration facade.
 *
 * Holds a map of HookHandlerInterface instances keyed by handler ID, fixed at
 * construction — the consumer states its handlers, matching the Scheduler's
 * consumer-states-its-backends pattern. Registration calls accept an optional
 * handler_id and route to the named handler. The constructor parameter default
 * supplies a single DirectHookHandler under the 'direct' ID — an omitted argument
 * yields it, while an explicit empty array registers no handlers — and 'direct' is
 * the handler used when no handler_id is specified.
 *
 * Components inject HooksService and call add_action() etc. to register hook callbacks.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final readonly class HooksService {
	// region FIELDS AND CONSTANTS

	/**
	 * Registered handlers indexed by ID.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     array<string, HookHandlerInterface>
	 */
	public array $handlers;

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * Omitting the argument registers a single DirectHookHandler under the 'direct' ID. An
	 * explicit array registers exactly those handlers, so an empty array yields a service
	 * with no handlers (useful for tests or fully-custom setups).
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<int, HookHandlerInterface> $initial_handlers Handlers to register, keyed into the map by each handler's ID. Defaults to a single DirectHookHandler.
	 */
	public function __construct( array $initial_handlers = array( new DirectHookHandler() ) ) {
		$handlers = array();
		foreach ( $initial_handlers as $handler ) {
			$handlers[ $handler->id ] = $handler;
		}

		$this->handlers = $handlers;
	}

	// endregion

	// region METHODS

	/**
	 * Wires every registered handler's one-time WordPress self-wiring, so one consumer
	 * call during boot prepares each handler (a scoped handler's start/end lifecycle,
	 * say) before components register hooks through the service.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function register_hooks(): void {
		foreach ( $this->handlers as $handler ) {
			$handler->register_hooks();
		}
	}

	/**
	 * Register a callback for a WordPress action via the chosen handler.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string   $hook           Action hook name.
	 * @param   callable $callback       Callback to register.
	 * @param   int      $priority       Hook priority (lower runs earlier).
	 * @param   int      $accepted_args  Number of arguments the callback accepts.
	 * @param   string   $handler_id     ID of the handler that should perform the registration.
	 */
	public function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1, string $handler_id = DirectHookHandler::DEFAULT_ID ): void {
		$this->resolve_handler( $handler_id )->add_action( $hook, $callback, $priority, $accepted_args );
	}

	/**
	 * Register a callback for a WordPress filter via the chosen handler.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string   $hook           Filter hook name.
	 * @param   callable $callback       Callback to register.
	 * @param   int      $priority       Hook priority (lower runs earlier).
	 * @param   int      $accepted_args  Number of arguments the callback accepts.
	 * @param   string   $handler_id     ID of the handler that should perform the registration.
	 */
	public function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1, string $handler_id = DirectHookHandler::DEFAULT_ID ): void {
		$this->resolve_handler( $handler_id )->add_filter( $hook, $callback, $priority, $accepted_args );
	}

	/**
	 * Remove a previously registered action callback via the chosen handler.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string   $hook        Action hook name.
	 * @param   callable $callback    Callback originally registered.
	 * @param   int      $priority    Hook priority used at registration.
	 * @param   string   $handler_id  ID of the handler that performed the registration.
	 *
	 * @return  bool
	 */
	public function remove_action( string $hook, callable $callback, int $priority = 10, string $handler_id = DirectHookHandler::DEFAULT_ID ): bool {
		return $this->resolve_handler( $handler_id )->remove_action( $hook, $callback, $priority );
	}

	/**
	 * Remove a previously registered filter callback via the chosen handler.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string   $hook        Filter hook name.
	 * @param   callable $callback    Callback originally registered.
	 * @param   int      $priority    Hook priority used at registration.
	 * @param   string   $handler_id  ID of the handler that performed the registration.
	 *
	 * @return  bool
	 */
	public function remove_filter( string $hook, callable $callback, int $priority = 10, string $handler_id = DirectHookHandler::DEFAULT_ID ): bool {
		return $this->resolve_handler( $handler_id )->remove_filter( $hook, $callback, $priority );
	}

	/**
	 * Remove every action registered via the chosen handler.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $handler_id ID of the handler whose actions to remove.
	 */
	public function remove_all_actions( string $handler_id = DirectHookHandler::DEFAULT_ID ): void {
		$this->resolve_handler( $handler_id )->remove_all_actions();
	}

	/**
	 * Remove every filter registered via the chosen handler.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $handler_id ID of the handler whose filters to remove.
	 */
	public function remove_all_filters( string $handler_id = DirectHookHandler::DEFAULT_ID ): void {
		$this->resolve_handler( $handler_id )->remove_all_filters();
	}

	// endregion

	// region HELPERS

	/**
	 * Look up a handler by ID, throwing if not found.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $id Handler ID.
	 *
	 * @return  HookHandlerInterface
	 *
	 * @throws  UnknownHookHandlerException When no handler is registered under $id.
	 */
	protected function resolve_handler( string $id ): HookHandlerInterface {
		$handler = $this->handlers[ $id ] ?? null;
		if ( null === $handler ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- framework-internal exception; never reaches an HTML output context unescaped.
			throw new UnknownHookHandlerException( "No hook handler is registered under id '$id'." );
		}
		return $handler;
	}

	// endregion
}
