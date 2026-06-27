<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\ObjectField;

use DeepWebSolutions\Framework\Settings\ObjectField\ValueObjects\ObjectMetaBox;

/**
 * Registers a per-entity meta box and reads/writes its fields as object meta.
 *
 * Fields are addressed by an explicit object id (an order or post) and an
 * explicit meta key. The field id and the storage key are kept distinct, so a
 * consumer can persist under runtime keys the settings id charset forbids.
 *
 * Contract-only in this package: its sole implementation lives in a higher consumer
 * package, kept there so this package takes on no dependency of its own. That
 * cross-package split justifies this lone-implementation interface — do not remove
 * it on a single-implementation count.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
interface ObjectFieldStoreInterface {
	/**
	 * Registers the meta box on its object screen.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   ObjectMetaBox $box Meta-box descriptor to register.
	 */
	public function register_meta_box( ObjectMetaBox $box ): void;

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
}
