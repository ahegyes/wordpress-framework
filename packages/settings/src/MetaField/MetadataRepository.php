<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\MetaField;

/**
 * Object-meta repository over WordPress's core metadata API.
 *
 * Targets one metadata object type — post, user, term, or comment, fixed at construction — and reads,
 * writes, and deletes through the matching get_metadata()/update_metadata()/delete_metadata() calls,
 * so a value follows the object whichever table backs its meta. CRUD keys off metadata_exists()
 * rather than value truthiness, so a stored empty value reads back as itself, not the supplied default.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final readonly class MetadataRepository implements ObjectMetaRepositoryInterface {
	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   MetaType $meta_type WordPress metadata object type this repository targets.
	 */
	public function __construct(
		protected MetaType $meta_type,
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
	public function get( int $object_id, string $meta_key, mixed $default_value = null ): mixed {
		return \metadata_exists( $this->meta_type->value, $object_id, $meta_key )
			? \get_metadata( $this->meta_type->value, $object_id, $meta_key, true )
			: $default_value;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function set( int $object_id, string $meta_key, mixed $value ): void {
		// update_metadata() unslashes both the meta key and the value; slash the key, and any string or
		// array value, first so backslashes survive the round-trip — get()/has() look the key up raw, so a
		// raw write would store it under a different key. Other value shapes pass through unchanged.
		$slashed = ( \is_string( $value ) || \is_array( $value ) ) ? \wp_slash( $value ) : $value;
		\update_metadata( $this->meta_type->value, $object_id, \wp_slash( $meta_key ), $slashed );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function has( int $object_id, string $meta_key ): bool {
		return \metadata_exists( $this->meta_type->value, $object_id, $meta_key );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function delete( int $object_id, string $meta_key ): bool {
		// delete_metadata() unslashes the key as update_metadata() does; slash it so a backslash key matches.
		return \delete_metadata( $this->meta_type->value, $object_id, \wp_slash( $meta_key ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function apply( int $object_id, array $sets, array $deletes ): void {
		foreach ( $sets as $meta_key => $value ) {
			$this->set( $object_id, (string) $meta_key, $value );
		}
		foreach ( $deletes as $meta_key ) {
			// metadata_exists() reads the object's meta cache, so skipping an absent key turns a batch of
			// never-set keys into one cache load rather than a direct, uncached delete query per key.
			if ( $this->has( $object_id, $meta_key ) ) {
				$this->delete( $object_id, $meta_key );
			}
		}
	}

	// endregion
}
