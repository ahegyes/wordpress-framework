<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Hooks\Handlers;

use DeepWebSolutions\Framework\Utilities\Hooks\Contracts\HookHandlerInterface;
use DeepWebSolutions\Framework\Utilities\Hooks\HookRegistry;

/**
 * Hook handler that auto-flushes between two WordPress hooks.
 *
 * Wraps a BufferedHookHandler with lifecycle hooks: when $start_hook fires, the buffer
 * is flushed (queued hooks registered with WordPress); when $end_hook fires, the buffer
 * is reset (registrations removed and queue cleared). If $end_hook is omitted, the
 * registrations persist for the rest of the request.
 *
 * Use this when a hook should only be live during a specific phase — e.g., admin-only
 * filters that should not affect the front end, or hooks scoped to a single REST request.
 *
 * Wire the start/end lifecycle by calling {@see self::register_lifecycle()} after
 * construction; this keeps the constructor side-effect-free for testability.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final readonly class ScopedHookHandler implements HookHandlerInterface {
	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string              $id          Handler ID.
	 * @param   string              $start_hook  WordPress hook on which to flush the buffer (register all queued hooks).
	 * @param   string              $end_hook    WordPress hook on which to reset the buffer. Empty to never reset.
	 * @param   BufferedHookHandler $buffer      Underlying buffered handler. Defaults to a fresh BufferedHookHandler with id "scoped-buffer".
	 */
	public function __construct(
		private string $id,
		private string $start_hook,
		private string $end_hook = '',
		private BufferedHookHandler $buffer = new BufferedHookHandler( 'scoped-buffer', new HookRegistry() ),
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
		$this->buffer->add_action( $hook, $callback, $priority, $accepted_args );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function add_filter( string $hook, callable $callback, int $priority, int $accepted_args ): void {
		$this->buffer->add_filter( $hook, $callback, $priority, $accepted_args );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function remove_action( string $hook, callable $callback, int $priority ): bool {
		return $this->buffer->remove_action( $hook, $callback, $priority );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function remove_filter( string $hook, callable $callback, int $priority ): bool {
		return $this->buffer->remove_filter( $hook, $callback, $priority );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function remove_all_actions(): void {
		$this->buffer->remove_all_actions();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function remove_all_filters(): void {
		$this->buffer->remove_all_filters();
	}

	// endregion

	// region METHODS

	/**
	 * Wire the start and end WordPress hooks to flush and reset the buffer.
	 *
	 * The flush/reset callbacks are stable [object, method] pairs, so WordPress
	 * de-duplicates repeat registrations and calling this more than once is harmless.
	 *
	 * The handler runs a single start->end cycle: reset() empties the queue, so a second
	 * start_hook firing re-registers nothing. Re-queue hooks after a reset for repeat use.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function register_lifecycle(): void {
		\add_action( $this->start_hook, array( $this->buffer, 'flush' ), 10, 0 );
		if ( '' !== $this->end_hook ) {
			\add_action( $this->end_hook, array( $this->buffer, 'reset' ), 10, 0 );
		}
	}

	// endregion

	// region GETTERS

	/**
	 * Return the start hook name.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  string
	 */
	public function get_start_hook(): string {
		return $this->start_hook;
	}

	/**
	 * Return the end hook name. Empty string means no end hook is wired.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  string
	 */
	public function get_end_hook(): string {
		return $this->end_hook;
	}

	/**
	 * Return the underlying buffered handler.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  BufferedHookHandler
	 */
	public function get_buffer(): BufferedHookHandler {
		return $this->buffer;
	}

	// endregion
}
