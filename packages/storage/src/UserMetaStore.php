<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Storage;

/**
 * Per-user key-value store backed by WordPress user_meta.
 *
 * Each instance owns one user_meta key. All entries are serialized into a single array
 * stored under that key. Operations target the current user by default; pass an explicit
 * $user_id to any method to target another user. When the resolved user is anonymous (ID 0),
 * writes are silent no-ops and reads return the default (or an empty array for {@see self::get_all()}).
 *
 * The $user_id parameter widens the KeyValueStoreInterface methods and is reachable only
 * through the concrete type — consumers targeting another user must typehint UserMetaStore,
 * not the interface.
 *
 * Useful for per-user state that must survive across requests, such as "this user has
 * dismissed admin notice X" tracking.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @template T
 *
 * @implements KeyValueStoreInterface<T>
 */
final readonly class UserMetaStore implements KeyValueStoreInterface {
	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $meta_key User_meta key under which all entries for this store live.
	 */
	public function __construct(
		protected string $meta_key,
	) {}

	// endregion

	// region INHERITED METHODS

	/**
	 * Persist a value under the given key for a user. Overwrites any existing value at the same key.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $key     Identifier under which to store the value.
	 * @param   mixed  $value   Value to persist.
	 * @param   int    $user_id User to target, or 0 for the current user. Defaults to 0.
	 */
	#[\Override]
	public function set( string $key, mixed $value, int $user_id = 0 ): void {
		$user_id = $this->resolve_user_id( $user_id );
		if ( $user_id < 1 ) {
			return;
		}
		$entries         = $this->load( $user_id );
		$entries[ $key ] = $value;
		$this->save( $user_id, $entries );
	}

	/**
	 * Retrieve a value by key for a user, or the default if no value is stored under it.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $key           Identifier to look up.
	 * @param   mixed  $default_value Value to return when no entry exists at $key. Defaults to null.
	 * @param   int    $user_id       User to target, or 0 for the current user. Defaults to 0.
	 *
	 * @return  mixed
	 */
	#[\Override]
	public function get( string $key, mixed $default_value = null, int $user_id = 0 ): mixed {
		$user_id = $this->resolve_user_id( $user_id );
		if ( $user_id < 1 ) {
			return $default_value;
		}
		$entries = $this->load( $user_id );
		return \array_key_exists( $key, $entries ) ? $entries[ $key ] : $default_value;
	}

	/**
	 * Check whether a value is stored at the given key for a user.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $key     Identifier to check.
	 * @param   int    $user_id User to target, or 0 for the current user. Defaults to 0.
	 *
	 * @return  bool
	 */
	#[\Override]
	public function has( string $key, int $user_id = 0 ): bool {
		$user_id = $this->resolve_user_id( $user_id );
		if ( $user_id < 1 ) {
			return false;
		}
		return \array_key_exists( $key, $this->load( $user_id ) );
	}

	/**
	 * Delete the value stored at the given key for a user.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $key     Identifier to delete.
	 * @param   int    $user_id User to target, or 0 for the current user. Defaults to 0.
	 *
	 * @return  bool True if a value was deleted, false if no value existed under the key.
	 */
	#[\Override]
	public function delete( string $key, int $user_id = 0 ): bool {
		$user_id = $this->resolve_user_id( $user_id );
		if ( $user_id < 1 ) {
			return false;
		}
		$entries = $this->load( $user_id );
		if ( ! \array_key_exists( $key, $entries ) ) {
			return false;
		}
		unset( $entries[ $key ] );
		$this->save( $user_id, $entries );
		return true;
	}

	/**
	 * Return all stored values for a user as a key-indexed array.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $user_id User to target, or 0 for the current user. Defaults to 0.
	 *
	 * @return  array<string, T>
	 */
	#[\Override]
	public function get_all( int $user_id = 0 ): array {
		$user_id = $this->resolve_user_id( $user_id );
		if ( $user_id < 1 ) {
			return array();
		}
		return $this->load( $user_id );
	}

	/**
	 * Remove every stored value for a user.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $user_id User to target, or 0 for the current user. Defaults to 0.
	 */
	#[\Override]
	public function clear( int $user_id = 0 ): void {
		$user_id = $this->resolve_user_id( $user_id );
		if ( $user_id < 1 ) {
			return;
		}
		\delete_user_meta( $user_id, $this->meta_key );
	}

	// endregion

	// region HELPERS

	/**
	 * Resolve a passed user ID to a concrete target, defaulting to the current user when 0.
	 * Non-positive results signal "no valid target" and are handled as no-ops by the callers.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $user_id Requested user ID, or 0 to target the current user.
	 *
	 * @return  int
	 */
	protected function resolve_user_id( int $user_id ): int {
		return 0 === $user_id ? \get_current_user_id() : $user_id;
	}

	/**
	 * Load the entries array from user_meta for the given user. Returns an empty array if
	 * the meta key doesn't exist or holds a non-array value.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $user_id User ID to load entries for.
	 *
	 * @return  array<string, T>
	 */
	protected function load( int $user_id ): array {
		$value = \get_user_meta( $user_id, $this->meta_key, true );
		return \is_array( $value ) ? $value : array();
	}

	/**
	 * Persist the entries array to user_meta for the given user.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int              $user_id User ID to persist entries for.
	 * @param   array<string, T> $entries Entries to persist.
	 */
	protected function save( int $user_id, array $entries ): void {
		// update_user_meta() runs the value through wp_unslash(); slash first so backslashes survive the round-trip.
		\update_user_meta( $user_id, $this->meta_key, \wp_slash( $entries ) );
	}

	// endregion
}
