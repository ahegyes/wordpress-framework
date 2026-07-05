<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Helpers;

/**
 * Request-shaping helpers.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class Request {
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
	public static function wp_parse_args_recursive( array $args, array $defaults ): array {
		$result = $defaults;

		foreach ( $args as $key => $value ) {
			if (
				\is_array( $value )
				&& isset( $result[ $key ] )
				&& \is_array( $result[ $key ] )
				&& ! \array_is_list( $value )
				&& ! \array_is_list( $result[ $key ] )
			) {
				$result[ $key ] = self::wp_parse_args_recursive( $value, $result[ $key ] );
				continue;
			}

			$result[ $key ] = $value;
		}

		return $result;
	}

	// endregion
}
