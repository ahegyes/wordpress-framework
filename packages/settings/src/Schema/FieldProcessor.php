<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Schema;

use DeepWebSolutions\Framework\Settings\Schema\Exceptions\UnknownFieldTypeException;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsField;

/**
 * Turns a field's raw submission into the value to persist.
 *
 * Pure and WordPress-free. Resolves the field type against the taxonomy
 * (rejecting an unknown type), coerces an absent submission to the type's empty
 * value (an empty array for a multi-value field, otherwise false — never null),
 * and for a present value applies the field's sanitizer, gates a choice value
 * against its resolved option set, then applies the field's own validator; a
 * value any step rejects falls back to the empty value.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class FieldProcessor {
	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   OptionsResolver $resolver Resolver for choice fields' option sets.
	 */
	public function __construct(
		private OptionsResolver $resolver = new OptionsResolver(),
	) {}

	// endregion

	// region METHODS

	/**
	 * Processes a field's submitted value into the value to persist.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   SettingsField        $field Field whose value is processed.
	 * @param   array<string, mixed> $input Raw submitted values keyed by field id; a missing key means the field was not submitted.
	 *
	 * @throws  UnknownFieldTypeException If the field declares a type outside the taxonomy.
	 *
	 * @return  mixed
	 */
	public function process( SettingsField $field, array $input ): mixed {
		$type = FieldType::tryFrom( $field->type );
		if ( null === $type ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- framework-internal exception; never reaches an HTML output context unescaped.
			throw new UnknownFieldTypeException( "Unknown settings field type: '$field->type'" );
		}

		$empty = FieldType::Multiselect === $type ? array() : false;

		if ( ! \array_key_exists( $field->id, $input ) ) {
			return $empty;
		}

		$value = $input[ $field->id ];

		// Every non-multiselect field expects a scalar submission; a tampered array would fatal a scalar
		// sanitizer (e.g. trim) or persist as the wrong type, so coerce a non-scalar to the empty value.
		if ( FieldType::Multiselect !== $type && ! \is_scalar( $value ) ) {
			return $empty;
		}

		if ( null !== $field->sanitize ) {
			$value = ( $field->sanitize )( $value );
		}

		if ( FieldType::Select === $type || FieldType::Radio === $type ) {
			if ( ! $this->is_option( $value, $field ) ) {
				return $empty;
			}
		} elseif ( FieldType::Multiselect === $type ) {
			$value = $this->filter_to_options( $value, $field );
		}

		if ( null !== $field->validate && ! ( $field->validate )( $value ) ) {
			return $empty;
		}

		return $value ?? $empty;
	}

	// endregion

	// region HELPERS

	/**
	 * Whether a single submitted value is a key in the field's resolved options.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed         $value Submitted value.
	 * @param   SettingsField $field Field whose options gate the value.
	 *
	 * @return  bool
	 */
	private function is_option( mixed $value, SettingsField $field ): bool {
		return ( \is_string( $value ) || \is_int( $value ) )
			&& \array_key_exists( $value, $this->resolver->resolve( $field->options ) );
	}

	/**
	 * Filters submitted values down to those that are keys in the field's resolved options.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed         $value Submitted value; a non-array yields an empty list.
	 * @param   SettingsField $field Field whose options gate the values.
	 *
	 * @return  list<int|string>
	 */
	private function filter_to_options( mixed $value, SettingsField $field ): array {
		$options = $this->resolver->resolve( $field->options );
		$values  = \is_array( $value ) ? $value : array();

		$valid = array();
		foreach ( $values as $candidate ) {
			if ( ( \is_string( $candidate ) || \is_int( $candidate ) ) && \array_key_exists( $candidate, $options ) ) {
				$valid[] = $candidate;
			}
		}

		return $valid;
	}

	// endregion
}
