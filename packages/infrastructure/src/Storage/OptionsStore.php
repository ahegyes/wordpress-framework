<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Storage;

/**
 * Cross-request key-value store backed by the WordPress options table.
 *
 * Each instance owns one wp_options row, identified by an option key passed at construction.
 * All entries for that store are serialized into a single array stored under that option.
 * State persists site-wide across requests and survives plugin deactivation unless cleared.
 * The option's autoload policy is configurable at construction; by default WordPress decides.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @template T
 *
 * @implements KeyValueStoreInterface<T>
 */
final readonly class OptionsStore implements KeyValueStoreInterface {
	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $option_key Option key under which all entries for this store live.
	 * @param   ?bool  $autoload   Autoload policy for the option row: true/false to force, null to let WordPress decide.
	 */
	public function __construct(
		protected string $option_key,
		protected ?bool $autoload = null,
	) {}

	// endregion

	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function get( string $key, mixed $default_value = null ): mixed {
		$entries = $this->load();
		return \array_key_exists( $key, $entries ) ? $entries[ $key ] : $default_value;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function set( string $key, mixed $value ): void {
		$entries         = $this->load();
		$entries[ $key ] = $value;
		$this->save( $entries );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function has( string $key ): bool {
		return \array_key_exists( $key, $this->load() );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function delete( string $key ): bool {
		$entries = $this->load();
		if ( ! \array_key_exists( $key, $entries ) ) {
			return false;
		}
		unset( $entries[ $key ] );
		$this->save( $entries );
		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function get_all(): array {
		return $this->load();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function clear(): void {
		\delete_option( $this->option_key );
	}

	// endregion

	// region HELPERS

	/**
	 * Loads the entries array from wp_options. Returns an empty array if the option doesn't
	 * exist or is corrupted (non-array value).
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  array<string, T>
	 */
	protected function load(): array {
		$value = \get_option( $this->option_key, array() );
		return \is_array( $value ) ? $value : array();
	}

	/**
	 * Persists the entries array to wp_options.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<string, T> $entries Entries to persist.
	 */
	protected function save( array $entries ): void {
		\update_option( $this->option_key, $entries, $this->autoload );
	}

	// endregion
}
