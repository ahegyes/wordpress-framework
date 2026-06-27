<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Storage;

/**
 * Generic key-value persistence abstraction.
 *
 * Implementations are typed by what they store (admin notices, settings, cache values, etc.)
 * and choose where they store it (in-memory per request, wp_options site-wide, user_meta
 * per-user, or any other backend that maps strings to values).
 *
 * Callers supply valid keys: a store passes the given key through unchanged and performs no
 * key sanitization of its own.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @template T
 */
interface KeyValueStoreInterface {
	/**
	 * Persist a value under the given key. Overwrites any existing value at the same key.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @phpstan-param T $value
	 *
	 * @param   string $key   Identifier under which to store the value.
	 * @param   mixed  $value Value to persist.
	 */
	public function set( string $key, mixed $value ): void;

	/**
	 * Retrieve a value by key, or the default if no value is stored under it.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @phpstan-param T|null $default_value
	 * @phpstan-return T|null
	 *
	 * @param   string $key           Identifier to look up.
	 * @param   mixed  $default_value Value to return when no entry exists at $key. Defaults to null.
	 *
	 * @return  mixed
	 */
	public function get( string $key, mixed $default_value = null ): mixed;

	/**
	 * Check whether a value is stored at the given key.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $key Identifier to check.
	 *
	 * @return  bool
	 */
	public function has( string $key ): bool;

	/**
	 * Delete the value stored at the given key.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $key Identifier to delete.
	 *
	 * @return  bool True if a value was deleted, false if no value existed under the key.
	 */
	public function delete( string $key ): bool;

	/**
	 * Return all stored values as a key-indexed array.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  array<string, T>
	 */
	public function get_all(): array;

	/**
	 * Remove every stored value.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function clear(): void;
}
