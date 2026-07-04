<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Caching;

use DeepWebSolutions\Framework\Utilities\Exceptions\InvalidGlobalNamePrefixException;

use function DeepWebSolutions\Framework\Utilities\is_valid_global_name_prefix;

/**
 * Runtime object cache with false-safe reads and versioned-group invalidation.
 *
 * Reads through wp_cache's $found flag so a cached false/null/0/'' reads back as a hit rather than a
 * miss, and {@see self::remember()} get-or-computes on that same basis. {@see self::flush()} advances a
 * stored generation suffix appended to the cache group, invalidating the whole group in one option write
 * - more robust than wp_cache_flush_group(), which silently no-ops on object caches without group support.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final readonly class ObjectCache {
	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $group Cache group all keys live under, before the generation suffix; also prefixes the generation option key.
	 *
	 * @throws  InvalidGlobalNamePrefixException If $group does not match the WordPress-global name charset.
	 */
	public function __construct(
		protected string $group,
	) {
		if ( ! is_valid_global_name_prefix( $group ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- framework-internal exception; never reaches an HTML output context unescaped.
			throw new InvalidGlobalNamePrefixException( "Invalid object cache group: '$group'. Use an optionally-underscore-prefixed lowercase name (a-z, 0-9, _, -) so cache group and option names stay well-formed." );
		}
	}

	// endregion

	// region METHODS

	/**
	 * Returns a cached value, or the default when no entry exists — a stored false/null/0/'' is a hit.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $key           Key to look up.
	 * @param   mixed  $default_value Value returned on a miss. Defaults to null.
	 *
	 * @return  mixed
	 */
	public function get( string $key, mixed $default_value = null ): mixed {
		$found = false;
		$value = \wp_cache_get( $key, $this->effective_group(), false, $found );

		return true === $found ? $value : $default_value;
	}

	/**
	 * Stores a value under a key with no expiration, so it lives until flushed or evicted.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $key   Key to store under.
	 * @param   mixed  $value Value to cache.
	 */
	public function set( string $key, mixed $value ): void {
		\wp_cache_set( $key, $value, $this->effective_group(), 0 );
	}

	/**
	 * Deletes one cached value from the current generation.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $key Key to delete.
	 */
	public function delete( string $key ): void {
		\wp_cache_delete( $key, $this->effective_group() );
	}

	/**
	 * Returns a cached value, computing and storing it via the callback on a miss — a cached falsey value
	 * is returned without recomputing.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string   $key      Key to look up.
	 * @param   callable $callback Producer invoked only on a miss; its return is cached. A thrown exception propagates and caches nothing.
	 *
	 * @return  mixed
	 */
	public function remember( string $key, callable $callback ): mixed {
		// Resolve the group once so a flush() during the callback orphans the store cleanly instead of
		// splitting the probe and the store across two generations, which would outlive the invalidation.
		$group = $this->effective_group();
		$found = false;
		$value = \wp_cache_get( $key, $group, false, $found );
		if ( true === $found ) {
			return $value;
		}

		$value = $callback();
		\wp_cache_set( $key, $value, $group, 0 );

		return $value;
	}

	/**
	 * Returns the cached values for several keys at once, keyed by key, with a missing key mapped to false.
	 * Unlike {@see self::get()}, a stored false/null/0/'' is indistinguishable from a missing key here —
	 * wp_cache_get_multiple() exposes no per-key found flag; read a key through get() when falsey-hit
	 * fidelity matters.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   list<string> $keys Keys to look up.
	 *
	 * @return  array<string, mixed>
	 */
	public function get_multiple( array $keys ): array {
		return \wp_cache_get_multiple( $keys, $this->effective_group() );
	}

	/**
	 * Invalidates every value in this cache's group by advancing the generation suffix; the prior
	 * generation's entries linger unreachable until the cache evicts them.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function flush(): void {
		\update_option( $this->suffix_key(), $this->suffix() + 1, false );
	}

	// endregion

	// region HELPERS

	/**
	 * Builds the cache group at the current generation, so a flush leaves earlier entries unreachable.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  string
	 */
	protected function effective_group(): string {
		return $this->group . '_' . $this->suffix();
	}

	/**
	 * Returns the current generation suffix, defaulting a missing or corrupt value to 1.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  int
	 */
	protected function suffix(): int {
		return \max( 1, (int) \get_option( $this->suffix_key(), 1 ) );
	}

	/**
	 * Returns the option key holding this cache's generation suffix.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  string
	 */
	protected function suffix_key(): string {
		return $this->group . '_object_cache_generation';
	}

	// endregion
}
