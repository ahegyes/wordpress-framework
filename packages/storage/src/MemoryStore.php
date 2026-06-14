<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Storage;

/**
 * In-memory key-value store. State lives in PHP memory for the duration of the request.
 *
 * Useful for transient queues (e.g., admin notices that only need to survive until render
 * later in the same request) and as a default for tests where persistence is unwanted.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @template T
 *
 * @implements KeyValueStoreInterface<T>
 */
final class MemoryStore implements KeyValueStoreInterface {
	// region FIELDS AND CONSTANTS

	/**
	 * Stored entries indexed by key.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     array<string, T>
	 */
	private array $entries = array();

	// endregion

	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function set( string $key, mixed $value ): void {
		$this->entries[ $key ] = $value;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function get( string $key, mixed $default_value = null ): mixed {
		return \array_key_exists( $key, $this->entries ) ? $this->entries[ $key ] : $default_value;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function has( string $key ): bool {
		return \array_key_exists( $key, $this->entries );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function delete( string $key ): bool {
		if ( ! \array_key_exists( $key, $this->entries ) ) {
			return false;
		}
		unset( $this->entries[ $key ] );
		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function get_all(): array {
		return $this->entries;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function clear(): void {
		$this->entries = array();
	}

	// endregion
}
