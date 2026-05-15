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
 * @return  array<string, mixed>
 */
function convert_to_primitives( \JsonSerializable $input_object ): array {
	$process_property_value = function ( mixed $value ) use ( &$process_property_value ) {
		return match ( true ) {
			\is_array( $value )                  => \array_map( $process_property_value, $value ),
			$value instanceof \BackedEnum        => $value->value,
			$value instanceof \DateTimeInterface => $value->format( \DateTimeInterface::ATOM ),
			$value instanceof \JsonSerializable  => $value->jsonSerialize(),
			default                              => $value,
		};
	};

	$result = array();
	foreach ( get_public_property_names( $input_object ) as $property_name ) {
		// @phpstan-ignore-next-line property.dynamicName
		$result[ $property_name ] = $process_property_value( $input_object->{ $property_name } );
	}

	return $result;
}
