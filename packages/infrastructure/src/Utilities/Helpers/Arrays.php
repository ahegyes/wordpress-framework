<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Helpers;

/**
 * Array helpers kept for non-trivial cross-plugin merge and insertion behavior.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class Arrays {
	// region METHODS

	/**
	 * Recursively merges arguments with defaults, treating list arrays as leaf values.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<array-key, mixed> $args     Provided arguments.
	 * @param   array<array-key, mixed> $defaults Default arguments.
	 *
	 * @return  array<array-key, mixed>
	 */
	public static function parse_args_recursive( array $args, array $defaults ): array {
		$result = $defaults;

		foreach ( $args as $key => $value ) {
			if (
				\is_array( $value )
				&& isset( $result[ $key ] )
				&& \is_array( $result[ $key ] )
				&& ! \array_is_list( $value )
				&& ! \array_is_list( $result[ $key ] )
			) {
				$result[ $key ] = self::parse_args_recursive( $value, $result[ $key ] );
				continue;
			}

			$result[ $key ] = $value;
		}

		return $result;
	}

	/**
	 * Inserts entries after a key while preserving associative keys.
	 *
	 * If the key is absent, entries are appended. List arrays retain list semantics and are reindexed by
	 * array_splice(), matching native PHP behavior for positional insertion.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<array-key, mixed> $items  Array to insert into.
	 * @param   int|string              $key    Key after which entries are inserted.
	 * @param   array<array-key, mixed> $insert Entries to insert.
	 *
	 * @return  array<array-key, mixed>
	 */
	public static function insert_after( array $items, int|string $key, array $insert ): array {
		$index    = \array_search( $key, \array_keys( $items ), true );
		$position = false === $index ? \count( $items ) : $index + 1;

		if ( array() === $items || ! \array_is_list( $items ) ) {
			return \array_slice( $items, 0, $position, true )
				+ $insert
				+ \array_slice( $items, $position, null, true );
		}

		\array_splice( $items, $position, 0, $insert );

		return $items;
	}

	// endregion
}
