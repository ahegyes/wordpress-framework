<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\EditScreen;

\defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the values an object form keeps for each object it edits.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
interface ObjectStoreInterface {
	/**
	 * Returns the object's stored values under those of the keys it has, so that a stored empty value differs from an absent key.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int          $object_id The object's id.
	 * @param   list<string> $keys      The keys to read.
	 *
	 * @return  array<string, mixed>
	 */
	public function get_values( int $object_id, array $keys ): array;

	/**
	 * Stores the values under their keys for the object, each as given.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int                  $object_id The object's id.
	 * @param   array<string, mixed> $values    The values, keyed by their keys.
	 *
	 * @return  void
	 */
	public function update_values( int $object_id, array $values ): void;
}
