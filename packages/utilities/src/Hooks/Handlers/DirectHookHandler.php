<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Hooks\Handlers;

use DeepWebSolutions\Framework\Utilities\Hooks\HookHandlerInterface;
use DeepWebSolutions\Framework\Utilities\Hooks\HookRegistry;

/**
 * Default hook handler — passes registrations directly to WordPress add_action / add_filter
 * and tracks them internally so remove_all_* can revert exhaustively.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final readonly class DirectHookHandler implements HookHandlerInterface {
	// region FIELDS AND CONSTANTS

	/**
	 * Default handler ID. HooksService routes unrouted registration calls here.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const DEFAULT_ID = 'direct';

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string       $id        Handler ID. Defaults to 'direct'.
	 * @param   HookRegistry $registry  Internal record store. Defaults to a fresh HookRegistry.
	 */
	public function __construct(
		private string $id = self::DEFAULT_ID,
		private HookRegistry $registry = new HookRegistry(),
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
		\add_action( $hook, $callback, $priority, $accepted_args );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function add_filter( string $hook, callable $callback, int $priority, int $accepted_args ): void {
		$this->registry->record_filter( $hook, $callback, $priority, $accepted_args );
		\add_filter( $hook, $callback, $priority, $accepted_args );
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

	// region GETTERS

	/**
	 * Return the internal registry. Exposed for inspection and for HooksService composition.
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
