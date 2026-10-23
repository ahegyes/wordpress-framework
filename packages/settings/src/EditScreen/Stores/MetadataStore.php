<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\EditScreen\Stores;

use DeepWebSolutions\Framework\Settings\EditScreen\ObjectStoreInterface;

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
			if ( \metadata_exists( $this->meta_type, $object_id, $key ) ) {
				$values[ $key ] = \get_metadata( $this->meta_type, $object_id, $key, true );
			}
		}

		return $values;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function update_values( int $object_id, array $values ): void {
		foreach ( $values as $key => $value ) {
			// The metadata API unslashes what it is given, which would strip a value's own backslashes.
			\update_metadata( $this->meta_type, $object_id, $key, \wp_slash( $value ) );
		}
	}

	// endregion
}
