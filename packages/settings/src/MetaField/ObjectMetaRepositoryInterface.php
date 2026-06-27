<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\MetaField;

/**
 * Reads, writes, and batch-applies an object's metadata, addressed by an explicit object id and meta key.
 *
 * The field id and the storage key stay distinct, so a consumer can persist under runtime keys the
 * settings id charset forbids. Beyond per-key CRUD, {@see self::apply()} is the batch primitive every
 * backend implements: it applies a group of writes and deletes together, so a backend whose object
 * persists as a unit writes K fields once rather than K times. Each metadata surface backs this
 * contract with its own implementation.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
interface ObjectMetaRepositoryInterface {
	/**
	 * Retrieves an object's stored meta value, or the default if none is stored.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int    $object_id     Object whose meta to read.
	 * @param   string $meta_key      Meta key to read.
	 * @param   mixed  $default_value Value to return when nothing is stored.
	 *
	 * @return  mixed
	 */
	public function get( int $object_id, string $meta_key, mixed $default_value = null ): mixed;

	/**
	 * Persists an object's meta value.
	 *
	 * The value round-trips verbatim; pass raw, unslashed values rather than slashed request data.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int    $object_id Object whose meta to write.
	 * @param   string $meta_key  Meta key to write.
	 * @param   mixed  $value     Value to persist.
	 */
	public function set( int $object_id, string $meta_key, mixed $value ): void;

	/**
	 * Whether a value is stored for an object's meta key.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int    $object_id Object to check.
	 * @param   string $meta_key  Meta key to check.
	 *
	 * @return  bool
	 */
	public function has( int $object_id, string $meta_key ): bool;

	/**
	 * Deletes an object's stored meta value.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int    $object_id Object whose meta to clear.
	 * @param   string $meta_key  Meta key to clear.
	 *
	 * @return  bool True if a value was deleted, false if none existed.
	 */
	public function delete( int $object_id, string $meta_key ): bool;

	/**
	 * Applies a batch of writes and deletes to an object, writes before deletes.
	 *
	 * A backend whose object persists as a unit commits the batch in a single write; a per-key backend
	 * applies each in turn, with no cross-key transaction. Writes run before deletes, so a key present in
	 * both is deleted.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int                     $object_id Object whose meta to update.
	 * @param   array<array-key, mixed> $sets      Values to write, keyed by meta key (a numeric key is normalized to a string).
	 * @param   list<string>            $deletes   Meta keys to delete.
	 */
	public function apply( int $object_id, array $sets, array $deletes ): void;
}
