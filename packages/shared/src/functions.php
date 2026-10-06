<?php declare( strict_types=1 );
/**
 * Defines the helper functions shared by the framework packages.
 *
 * @since   2.0.0
 * @version 2.0.0
 * @package DeepWebSolutions\Framework\Shared
 */

namespace DeepWebSolutions\Framework\Shared;

/**
 * Inserts entries after a key, or appends them when the key is missing, reindexing a list and keeping the keys of any other array.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   array<array-key, mixed> $items  The array to insert into.
 * @param   int|string              $key    The key to insert after.
 * @param   array<array-key, mixed> $insert The entries to insert.
 *
 * @return  array<array-key, mixed>
 */
function insert_after( array $items, int|string $key, array $insert ): array {
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

/**
 * Replaces each {key} placeholder in a log message with its scalar, null or Stringable context value.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string                  $message The message with placeholders.
 * @param   array<array-key, mixed> $context The values to interpolate.
 *
 * @return  string
 */
function interpolate_log_message( string $message, array $context ): string {
	$replacements = array();
	foreach ( $context as $key => $value ) {
		if ( \is_null( $value ) || \is_scalar( $value ) || $value instanceof \Stringable ) {
			$replacements[ '{' . $key . '}' ] = (string) $value;
		}
	}

	return \strtr( $message, $replacements );
}

/**
 * Returns a value as an integer, or null when it does not hold one.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   mixed $value The value to parse.
 *
 * @return  int|null
 */
function parse_int( mixed $value ): ?int {
	return \filter_var( $value, \FILTER_VALIDATE_INT, \FILTER_NULL_ON_FAILURE );
}
