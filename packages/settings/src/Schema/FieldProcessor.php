<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Schema;

use DeepWebSolutions\Framework\Settings\Schema\Exceptions\UnknownFieldTypeException;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\CustomFieldType;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsField;

/**
 * Turns a field's raw submission into the value to persist.
 *
 * Pure and WordPress-free. Resolves the field type against the taxonomy
 * (rejecting an unknown type), coerces an absent submission to the type's empty
 * value (an empty array for a multi-value field, otherwise false — never null),
 * and for a present value applies the field's sanitizer, gates a choice value
 * against its resolved option set, then applies the field's own validator; a
 * value any step rejects falls back to the empty value. A type outside the
 * taxonomy but present in the injected custom-type registry is processed as a
 * plain scalar through the field's own sanitize/validate, falling back to the
 * field's default (a non-scalar submission is coerced to the default before the
 * sanitizer runs, mirroring the built-in scalar guard); a type in neither still throws.
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
	 * @param   OptionsResolver                $resolver     Resolver for choice fields' option sets.
	 * @param   array<string, CustomFieldType> $custom_types Registry of types outside the taxonomy whose submissions are accepted, keyed by type token.
	 */
	public function __construct(
		protected OptionsResolver $resolver = new OptionsResolver(),
		protected array $custom_types = array(),
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
	 * @throws  UnknownFieldTypeException If the field declares a type outside both the taxonomy and the custom-type registry.
	 *
	 * @return  mixed
	 */
	public function process( SettingsField $field, array $input ): mixed {
		$type = FieldType::tryFrom( $field->type );
		if ( null === $type ) {
			if ( isset( $this->custom_types[ $field->type ] ) ) {
				return $this->process_custom( $field, $input );
			}
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
	 * Processes a custom-typed field as a plain scalar: an absent or non-scalar submission yields the field's
	 * default, a present scalar runs the field's own sanitize then validate, falling back to the default when validation rejects.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   SettingsField        $field Field whose value is processed.
	 * @param   array<string, mixed> $input Raw submitted values keyed by field id.
	 *
	 * @return  mixed
	 */
	protected function process_custom( SettingsField $field, array $input ): mixed {
		if ( ! \array_key_exists( $field->id, $input ) ) {
			return $field->default_value;
		}

		$value = $input[ $field->id ];

		// A custom type is treated as scalar: a tampered array submission would fatal a scalar sanitizer
		// (e.g. trim), so coerce a non-scalar to the field's default — mirroring the built-in scalar guard.
		if ( ! \is_scalar( $value ) ) {
			return $field->default_value;
		}

		return $this->sanitize_and_validate( $field, $value, $field->default_value );
	}

	/**
	 * Runs a field's sanitize then validate, returning the fallback when validation rejects the value.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   SettingsField $field    Field whose closures to apply.
	 * @param   mixed         $value    Value to sanitize and validate.
	 * @param   mixed         $rejected Value returned when validation rejects.
	 *
	 * @return  mixed
	 */
	protected function sanitize_and_validate( SettingsField $field, mixed $value, mixed $rejected ): mixed {
		if ( null !== $field->sanitize ) {
			$value = ( $field->sanitize )( $value );
		}
		if ( null !== $field->validate && ! ( $field->validate )( $value ) ) {
			return $rejected;
		}

		return $value;
	}

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
	protected function is_option( mixed $value, SettingsField $field ): bool {
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
	protected function filter_to_options( mixed $value, SettingsField $field ): array {
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
