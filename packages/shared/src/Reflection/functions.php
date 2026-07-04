<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Shared\Reflection;

/**
 * Returns the names of all public properties declared on the given object's class.
 *
 * Results are memoized per class, so repeated calls reuse a single
 * {@see \ReflectionClass} traversal.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   object $input_object The object to inspect.
 *
 * @return  array<int, non-empty-string>
 */
function get_public_property_names( object $input_object ): array {
	static $properties = array();

	$object_class = \get_class( $input_object );
	if ( ! isset( $properties[ $object_class ] ) ) {
		$properties[ $object_class ] = \array_map(
			static fn ( \ReflectionProperty $property ) => $property->getName(),
			new \ReflectionClass( $input_object )->getProperties( \ReflectionProperty::IS_PUBLIC )
		);
	}

	return $properties[ $object_class ];
}

/**
 * Converts a {@see \JsonSerializable} object to an associative array of primitive values
 * keyed by public property name. Nested {@see \JsonSerializable} values are recursively
 * converted; {@see \BackedEnum} values are reduced to their `value`; {@see \DateTimeInterface}
 * values are formatted via {@see \DateTimeInterface::ATOM}.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   \JsonSerializable $input_object The object to convert.
 *
 * @throws  \RuntimeException If the object graph is cyclic: a nested object re-entered while its own expansion is still in flight.
 *
 * @return  array<string, mixed>
 */
function convert_to_primitives( \JsonSerializable $input_object ): array {
	// Objects whose jsonSerialize() expansion is in flight, keyed by spl_object_id(). Function-static
	// so the guard survives the mutual recursion through consumer jsonSerialize() implementations,
	// turning a cyclic object graph into a diagnosable throw instead of stack exhaustion. Every cycle
	// must re-enter the expansion below, so guarding there suffices; the same instance at sibling
	// positions is unmarked again by the time the sibling is reduced. The guard assumes
	// jsonSerialize() is pure: a serializer with side effects that starts an independent conversion
	// of a graph referencing an in-flight object reads as a cycle.
	static $expanding = array();

	$process_property_value = function ( mixed $value ) use ( &$process_property_value, &$expanding ) {
		if ( \is_array( $value ) ) {
			return \array_map( $process_property_value, $value );
		}
		if ( $value instanceof \BackedEnum ) {
			return $value->value;
		}
		if ( $value instanceof \DateTimeInterface ) {
			return $value->format( \DateTimeInterface::ATOM );
		}
		if ( $value instanceof \JsonSerializable ) {
			$object_id = \spl_object_id( $value );
			if ( isset( $expanding[ $object_id ] ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- framework-internal exception; never reaches an HTML output context unescaped.
				throw new \RuntimeException( 'Cyclic object graph: ' . \get_class( $value ) . ' is already being converted to primitives.' );
			}

			$expanding[ $object_id ] = true;
			try {
				return $process_property_value( $value->jsonSerialize() );
			} finally {
				unset( $expanding[ $object_id ] );
			}
		}

		return $value;
	};

	$result = array();
	foreach ( namespace\get_public_property_names( $input_object ) as $property_name ) {
		// @phpstan-ignore-next-line property.dynamicName
		$result[ $property_name ] = $process_property_value( $input_object->{ $property_name } );
	}

	return $result;
}
