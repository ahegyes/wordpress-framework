<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\EditScreen\Stores;

use DeepWebSolutions\Framework\Core\Storage\StoredData;
use DeepWebSolutions\Framework\Settings\EditScreen\ObjectStoreInterface;
use DeepWebSolutions\Framework\Shared\Exception\InvalidArgumentException;

\defined( 'ABSPATH' ) || exit;

/**
 * Keeps an object form's values as the object's metadata, one meta key per field.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class MetadataStore implements ObjectStoreInterface {
	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $meta_type The metadata API's type of the objects, such as post, user or term.
	 */
	public function __construct(
		protected string $meta_type
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
	public function get_values( int $object_id, array $keys ): array {
		$values = array();
		foreach ( $keys as $key ) {
			$value = get_metadata_raw( $this->meta_type, $object_id, $key, true ); // A NULL row reads as absent, as the metadata API reads it, where get_metadata() would return the registered default.
			if ( ! \is_null( $value ) ) {
				$values[ $key ] = $value;
			}
		}

		return $values;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @throws  InvalidArgumentException Thrown when a value holds anything but scalars, null and arrays, before any value is stored.
	 */
	#[\Override]
	public function update_values( int $object_id, array $values ): void {
		StoredData::check_values( $values ); // The metadata API rewrites an object's public properties as it unslashes them.
		foreach ( $values as $key => $value ) {
			update_metadata( $this->meta_type, $object_id, $key, wp_slash( $value ) ); // The metadata API unslashes what it is given, which would strip a value's own backslashes.
		}
	}

	// endregion
}
