<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Hooks\Handlers;

use DeepWebSolutions\Framework\Utilities\Hooks\HookHandlerInterface;
use DeepWebSolutions\Framework\Utilities\Hooks\HookRegistry;

/**
 * Hook handler that queues registrations without immediately calling WordPress.
 *
 * Plugin code calls {@see self::flush()} (typically wired to a WordPress hook like
 * 'plugins_loaded' or 'init') to register every queued hook with WordPress in one
 * sweep. {@see self::reset()} reverses the registration and clears the queue.
 *
 * Only additions are buffered. Removals take effect immediately: they drop the record
 * from the queue and unregister any callback already flushed to WordPress (a no-op
 * before flush), keeping the handler consistent with {@see DirectHookHandler}.
 *
 * Useful when registration timing must be deferred — e.g., when a component is
 * constructed before WordPress has loaded the plugins it depends on, or when the
 * full hook list isn't known until later in the request.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final readonly class BufferedHookHandler implements HookHandlerInterface {
	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string       $id        Handler ID. Defaults to 'buffered'.
	 * @param   HookRegistry $registry  Internal record store. Defaults to a fresh HookRegistry.
	 */
	public function __construct(
		protected string $id = 'buffered',
		protected HookRegistry $registry = new HookRegistry(),
	) {}

	// endregion

	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function get_id(): string {
		return $this->id;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function add_action( string $hook, callable $callback, int $priority, int $accepted_args ): void {
		$this->registry->record_action( $hook, $callback, $priority, $accepted_args );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function add_filter( string $hook, callable $callback, int $priority, int $accepted_args ): void {
		$this->registry->record_filter( $hook, $callback, $priority, $accepted_args );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function remove_action( string $hook, callable $callback, int $priority ): bool {
		$forgotten = $this->registry->forget_action( $hook, $callback, $priority );
		\remove_action( $hook, $callback, $priority );
		return $forgotten;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function remove_filter( string $hook, callable $callback, int $priority ): bool {
		$forgotten = $this->registry->forget_filter( $hook, $callback, $priority );
		\remove_filter( $hook, $callback, $priority );
		return $forgotten;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function remove_all_actions(): void {
		foreach ( $this->registry->get_actions() as $record ) {
			\remove_action( $record['hook'], $record['callback'], $record['priority'] );
		}
		$this->registry->clear_actions();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function remove_all_filters(): void {
		foreach ( $this->registry->get_filters() as $record ) {
			\remove_filter( $record['hook'], $record['callback'], $record['priority'] );
		}
		$this->registry->clear_filters();
	}

	// endregion

	// region METHODS

	/**
	 * Register every queued hook with WordPress.
	 *
	 * Idempotency: calling flush() multiple times re-registers the same hooks. WordPress
	 * de-duplicates identical (hook, callback, priority) triples, so duplicate flushes
	 * are harmless but wasteful. Call once per intended registration cycle.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function flush(): void {
		foreach ( $this->registry->get_actions() as $record ) {
			\add_action( $record['hook'], $record['callback'], $record['priority'], $record['accepted_args'] );
		}
		foreach ( $this->registry->get_filters() as $record ) {
			\add_filter( $record['hook'], $record['callback'], $record['priority'], $record['accepted_args'] );
		}
	}

	/**
	 * Un-register every queued hook with WordPress and clear the queue.
	 *
	 * Pairs with {@see self::flush()} — only meaningful after a flush has registered
	 * the queued hooks. Clearing the queue afterwards prevents the handler from being
	 * re-flushed with the same now-removed hooks.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function reset(): void {
		foreach ( $this->registry->get_actions() as $record ) {
			\remove_action( $record['hook'], $record['callback'], $record['priority'] );
		}
		foreach ( $this->registry->get_filters() as $record ) {
			\remove_filter( $record['hook'], $record['callback'], $record['priority'] );
		}
		$this->registry->clear_actions();
		$this->registry->clear_filters();
	}

	// endregion

	// region GETTERS

	/**
	 * Return the internal registry. Exposed for inspection and composition by ScopedHookHandler.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  HookRegistry
	 */
	public function get_registry(): HookRegistry {
		return $this->registry;
	}

	// endregion
}
