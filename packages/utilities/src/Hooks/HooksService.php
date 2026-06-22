<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Hooks;

use DeepWebSolutions\Framework\Utilities\Hooks\Handlers\DirectHookHandler;
use OutOfBoundsException;

/**
 * Multi-handler hook registration facade.
 *
 * Holds a registry of HookHandlerInterface instances keyed by ID. Registration calls
 * accept an optional handler_id and route to the named handler. The default handler
 * is 'direct' — registered automatically on construction and used when no handler_id
 * is specified.
 *
 * Components inject HooksService and call add_action() etc. to register hook callbacks.
 * Plugins that need buffered or scoped registration register additional handlers via
 * {@see self::register_handler()} during plugin boot.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class HooksService {
	// region FIELDS AND CONSTANTS

	/**
	 * Registered handlers indexed by ID.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     array<string, HookHandlerInterface>
	 */
	private(set) array $handlers = array();

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * Null (the default) registers a single DirectHookHandler under the 'direct' ID. An
	 * explicit array registers exactly those handlers, so an empty array yields a service
	 * with no handlers (useful for tests or fully-custom setups).
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<int, HookHandlerInterface>|null $initial_handlers Handlers to register up front, or null for a single default DirectHookHandler.
	 */
	public function __construct( ?array $initial_handlers = null ) {
		$handlers = $initial_handlers ?? array( new DirectHookHandler() );
		foreach ( $handlers as $handler ) {
			$this->register_handler( $handler );
		}
	}

	// endregion

	// region METHODS

	/**
	 * Register a handler with the service. Subsequent calls naming this handler's ID
	 * will route through it.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   HookHandlerInterface $handler Handler to register.
	 */
	public function register_handler( HookHandlerInterface $handler ): void {
		$this->handlers[ $handler->get_id() ] = $handler;
	}

	/**
	 * Look up a registered handler by ID.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $id Handler ID.
	 *
	 * @return  HookHandlerInterface|null
	 */
	public function get_handler( string $id ): ?HookHandlerInterface {
		return $this->handlers[ $id ] ?? null;
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
	 * @throws  OutOfBoundsException When no handler is registered under $id.
	 */
	protected function resolve_handler( string $id ): HookHandlerInterface {
		$handler = $this->get_handler( $id );
		if ( null === $handler ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- framework-internal exception; never reaches an HTML output context unescaped.
			throw new OutOfBoundsException( "No hook handler is registered under id '$id'." );
		}
		return $handler;
	}

	// endregion
}
