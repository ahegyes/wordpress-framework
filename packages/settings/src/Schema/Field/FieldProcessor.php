<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Schema\Field;

use DeepWebSolutions\Framework\Settings\Schema\Errors\FieldProcessingError;
use DeepWebSolutions\Framework\Settings\Schema\Exceptions\UnknownFieldTypeException;
use DeepWebSolutions\Framework\Settings\Schema\Options\OptionsResolver;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\CustomFieldType;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsField;
use DeepWebSolutions\Framework\Shared\Result\AbstractResult;
use DeepWebSolutions\Framework\Shared\Result\Failure;
use DeepWebSolutions\Framework\Shared\Result\Success;

/**
 * Pure, WordPress-free field-submission processor.
 *
 * Turns a field's raw submitted value into either the value to persist or a structured rejection.
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
	 * @param   OptionsResolver                $resolver        Resolver for choice fields' option sets.
	 * @param   array<string, CustomFieldType> $custom_types    Registry of types outside the taxonomy whose submissions are accepted, keyed by type token.
	 * @param   array<string, \Closure>        $type_sanitizers Default sanitizer per built-in type token, applied when a field declares no sanitizer of its own.
	 */
	public function __construct(
		protected OptionsResolver $resolver = new OptionsResolver(),
		protected array $custom_types = array(),
		protected array $type_sanitizers = array(),
	) {}

	// endregion

	// region METHODS

	/**
	 * Processes a field's submitted value into the value to persist, folding a rejection to a fallback value.
	 *
	 * Convenience over {@see self::process_or_reject()} for callers that do not distinguish a rejected
	 * submission from a valid-empty one; a rejected value becomes the field type's empty value, or the field's
	 * declared default for a custom type.
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
		return $this->process_or_reject( $field, $input )->match(
			static fn ( mixed $value ): mixed => $value,
			fn ( FieldProcessingError $error ): mixed => $this->fallback_value( $field ), // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- the rejection cause is folded to a fallback value.
		);
	}

	/**
	 * Processes a field's submitted value, returning the value to persist or the cause of its rejection.
	 *
	 * An absent submission is a success carrying the type's empty value (an unchecked checkbox is false);
	 * a present value that fails the shape, option, or validation gate is a failure naming the field, so a
	 * caller can preserve the field's prior value instead of overwriting it.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   SettingsField        $field Field whose value is processed.
	 * @param   array<string, mixed> $input Raw submitted values keyed by field id; a missing key means the field was not submitted.
	 *
	 * @throws  UnknownFieldTypeException If the field declares a type outside both the taxonomy and the custom-type registry.
	 *
	 * @return  Success<mixed>|Failure<FieldProcessingError> The value to persist, or the cause of its rejection.
	 */
	#[\NoDiscard( 'a rejected field submission must be handled, not dropped' )]
	public function process_or_reject( SettingsField $field, array $input ): AbstractResult {
		$type = FieldType::tryFrom( $field->type );
		if ( null === $type ) {
			if ( isset( $this->custom_types[ $field->type ] ) ) {
				return $this->process_custom_or_reject( $field, $input );
			}
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- framework-internal exception; never reaches an HTML output context unescaped.
			throw new UnknownFieldTypeException( "Unknown settings field type: '$field->type'" );
		}

		if ( ! \array_key_exists( $field->id, $input ) ) {
			return Success::from( $this->empty_value( $field ) );
		}

		$value = $input[ $field->id ];

		// A value already equal to the type's own empty is idempotent: WordPress sanitizes a registered
		// option twice when it is first created (update_option then add_option), and the second pass sees
		// the first pass's empty (false / array()) as a present value — re-running the sanitizer would flip
		// false to '' and the option gate would reject it. Short-circuit so the empty round-trips unchanged.
		if ( $value === $this->empty_value( $field ) ) {
			return Success::from( $value );
		}

		// Every non-multiselect field expects a scalar submission; a tampered array would fatal a scalar
		// sanitizer (e.g. trim) or persist as the wrong type, so a non-scalar is rejected.
		if ( FieldType::Multiselect !== $type && ! \is_scalar( $value ) ) {
			return Failure::from( new FieldProcessingError( $field->id, FieldProcessingErrorReason::UnexpectedShape ) );
		}

		$sanitize = $field->sanitize ?? ( $this->type_sanitizers[ $field->type ] ?? null );
		if ( null !== $sanitize ) {
			$value = $sanitize( $value );
		}

		if ( FieldType::Select === $type || FieldType::Radio === $type ) {
			if ( ! $this->is_option( $value, $field ) ) {
				return Failure::from( new FieldProcessingError( $field->id, FieldProcessingErrorReason::NotAnOption ) );
			}
		} elseif ( FieldType::Multiselect === $type ) {
			$value = $this->filter_to_options( $value, $field );
		}

		if ( null !== $field->validate && ! ( $field->validate )( $value ) ) {
			return Failure::from( new FieldProcessingError( $field->id, FieldProcessingErrorReason::FailedValidation ) );
		}

		return Success::from( $value ?? $this->empty_value( $field ) );
	}

	// endregion

	// region HELPERS

	/**
	 * Processes a custom-typed field as a plain scalar, returning the value to persist or its rejection.
	 *
	 * An absent submission is a success carrying the field's default; a present non-scalar, or a value the
	 * field's validator refuses, is a failure, so a backend can preserve the field's prior value.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   SettingsField        $field Field whose value is processed.
	 * @param   array<string, mixed> $input Raw submitted values keyed by field id.
	 *
	 * @return  Success<mixed>|Failure<FieldProcessingError> The value to persist, or the cause of its rejection.
	 */
	protected function process_custom_or_reject( SettingsField $field, array $input ): AbstractResult {
		if ( ! \array_key_exists( $field->id, $input ) ) {
			return Success::from( $field->default_value );
		}

		$value = $input[ $field->id ];

		// A custom type is treated as scalar: a tampered array submission would fatal a scalar sanitizer
		// (e.g. trim), so a non-scalar is rejected — mirroring the built-in scalar guard.
		if ( ! \is_scalar( $value ) ) {
			return Failure::from( new FieldProcessingError( $field->id, FieldProcessingErrorReason::UnexpectedShape ) );
		}

		if ( null !== $field->sanitize ) {
			$value = ( $field->sanitize )( $value );
		}
		if ( null !== $field->validate && ! ( $field->validate )( $value ) ) {
			return Failure::from( new FieldProcessingError( $field->id, FieldProcessingErrorReason::FailedValidation ) );
		}

		return Success::from( $value );
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

	/**
	 * The empty value for a field's type: an empty array for a multi-value field, otherwise false — never null.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   SettingsField $field Field whose type determines the empty value.
	 *
	 * @return  array<array-key, mixed>|false
	 */
	protected function empty_value( SettingsField $field ): array|false {
		return FieldType::Multiselect->value === $field->type ? array() : false;
	}

	/**
	 * The value {@see self::process()} folds a rejection to: a custom type's declared default, otherwise the
	 * field type's empty value — preserving the pre-Result behavior for each.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   SettingsField $field Field whose rejection fallback to resolve.
	 *
	 * @return  mixed
	 */
	protected function fallback_value( SettingsField $field ): mixed {
		return isset( $this->custom_types[ $field->type ] ) ? $field->default_value : $this->empty_value( $field );
	}

	// endregion
}
