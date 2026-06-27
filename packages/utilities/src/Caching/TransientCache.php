<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Caching;

/**
 * Per-plugin transient cache with versioned-group invalidation.
 *
 * Namespaces every key under a plugin-supplied prefix and an autoloaded generation suffix, so
 * {@see flush()} invalidates the whole group in one option write — no key enumeration. Stored values
 * are wrapped so a cached false/null/0/'' reads back as a hit rather than a transient miss. A key
 * whose full name would exceed WordPress' 172-character transient-name limit is skipped with a
 * _doing_it_wrong() notice. Every entry requires a positive expiration, so each row carries a timeout
 * and is reclaimed by WordPress' daily transient cleanup once it lapses — including a prior generation
 * left unreachable by a flush; the generation suffix gives instant group invalidation independent of
 * that cleanup.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final readonly class TransientCache {
	// region FIELDS AND CONSTANTS

	/**
	 * Array key wrapping a cached value, so a stored false/null/0/'' reads back as a hit, not a miss.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const PAYLOAD = '__dws_transient_cache_payload__';

	/**
	 * Maximum full transient-name length: option_name is varchar(191), less the '_transient_timeout_' prefix.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     int
	 */
	protected const MAX_KEY_LENGTH = 172;

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $key_prefix Per-plugin namespace prepended to every key (a safe slug).
	 */
	public function __construct(
		protected string $key_prefix,
	) {}

	// endregion

	// region METHODS

	/**
	 * Returns a cached value, or the default when no live entry exists.
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
		$full_key = $this->full_key( $key );
		if ( ! $this->is_within_length( $full_key ) ) {
			return $default_value;
		}

		$probe = $this->probe( $full_key );
		return $probe['hit'] ? $probe['value'] : $default_value;
	}

	/**
	 * Stores a value under a key, expiring it after a number of seconds.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $key        Key to store under.
	 * @param   mixed  $value      Value to cache.
	 * @param   int    $expiration Lifetime in seconds; must be positive — a non-positive value is rejected and the write skipped.
	 */
	public function set( string $key, mixed $value, int $expiration ): void {
		$full_key = $this->full_key( $key );
		if ( ! $this->is_within_length( $full_key ) ) {
			return;
		}

		$this->store( $full_key, $value, $expiration );
	}

	/**
	 * Deletes a cached value.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $key Key to delete.
	 *
	 * @return  bool True when an entry existed and was removed, false otherwise.
	 */
	public function delete( string $key ): bool {
		$full_key = $this->full_key( $key );
		if ( ! $this->is_within_length( $full_key ) ) {
			return false;
		}

		return \delete_transient( $full_key );
	}

	/**
	 * Returns a cached value, computing and storing it via the callback on a miss.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string   $key        Key to look up.
	 * @param   callable $callback   Producer invoked only on a miss; its return is cached. A thrown exception propagates and caches nothing.
	 * @param   int      $expiration Lifetime in seconds for a freshly computed value; must be positive — a non-positive value is rejected and the write skipped.
	 *
	 * @return  mixed
	 */
	public function remember( string $key, callable $callback, int $expiration ): mixed {
		// Resolve the key once so a concurrent flush() mid-call orphans the write cleanly instead of
		// splitting the probe and the store across two generations.
		$full_key = $this->full_key( $key );
		if ( ! $this->is_within_length( $full_key ) ) {
			return $callback();
		}

		$probe = $this->probe( $full_key );
		if ( $probe['hit'] ) {
			return $probe['value'];
		}

		$value = $callback();
		$this->store( $full_key, $value, $expiration );
		return $value;
	}

	/**
	 * Invalidates every value in this cache's group by advancing the generation suffix.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function flush(): void {
		\update_option( $this->suffix_key(), $this->suffix() + 1, true );
	}

	// endregion

	// region HELPERS

	/**
	 * Wraps and writes a value under a fully-resolved transient name.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $full_key   Resolved transient name.
	 * @param   mixed  $value      Value to wrap and store.
	 * @param   int    $expiration Positive lifetime in seconds.
	 */
	protected function store( string $full_key, mixed $value, int $expiration ): void {
		if ( ! $this->is_positive_expiration( $expiration ) ) {
			return;
		}

		\set_transient( $full_key, array( self::PAYLOAD => $value ), $expiration );
	}

	/**
	 * Reads the raw transient and reports whether it is a framework-owned hit.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $full_key Resolved transient name.
	 *
	 * @return  array{hit: bool, value: mixed}
	 */
	protected function probe( string $full_key ): array {
		$raw = \get_transient( $full_key );
		if ( \is_array( $raw ) && \array_key_exists( self::PAYLOAD, $raw ) && \count( $raw ) === 1 ) {
			return array(
				'hit'   => true,
				'value' => $raw[ self::PAYLOAD ],
			);
		}

		return array(
			'hit'   => false,
			'value' => null,
		);
	}

	/**
	 * Builds the namespaced transient name for a user key at the current generation.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $key User key.
	 *
	 * @return  string
	 */
	protected function full_key( string $key ): string {
		return $this->key_prefix . '/' . $key . '__' . $this->suffix();
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
		return $this->key_prefix . '_cache_invalidation_suffix';
	}

	/**
	 * Reports whether a full transient name fits WordPress' length limit, warning when it does not.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $full_key Resolved transient name.
	 *
	 * @return  bool
	 */
	protected function is_within_length( string $full_key ): bool {
		if ( \strlen( $full_key ) <= self::MAX_KEY_LENGTH ) {
			return true;
		}

		\_doing_it_wrong(
			__METHOD__,
			\esc_html(
				\sprintf(
					'A transient key for prefix "%s" is %d characters, over the %d-character limit; caching is skipped.',
					$this->key_prefix,
					\strlen( $full_key ),
					self::MAX_KEY_LENGTH
				)
			),
			'2.0.0'
		);
		return false;
	}

	/**
	 * Reports whether an expiration is positive, warning when it is not.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $expiration Lifetime in seconds.
	 *
	 * @return  bool
	 */
	protected function is_positive_expiration( int $expiration ): bool {
		if ( $expiration >= 1 ) {
			return true;
		}

		\_doing_it_wrong(
			__METHOD__,
			\esc_html(
				\sprintf(
					'A transient for prefix "%s" needs a positive expiration; %d given, so caching is skipped.',
					$this->key_prefix,
					$expiration
				)
			),
			'2.0.0'
		);
		return false;
	}

	// endregion
}
