<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Tests\Unit;

use DeepWebSolutions\Framework\Settings\Schema\Errors\FieldProcessingError;
use DeepWebSolutions\Framework\Settings\Schema\Exceptions\UnknownFieldTypeException;
use DeepWebSolutions\Framework\Settings\Schema\Field\FieldProcessingErrorReason;
use DeepWebSolutions\Framework\Settings\Schema\Field\FieldProcessor;
use DeepWebSolutions\Framework\Settings\Schema\Options\OptionsResolver;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\CustomFieldType;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsField;
use DeepWebSolutions\Framework\Shared\Result\Failure;
use DeepWebSolutions\Framework\Shared\Result\Success;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesFunction;
use PHPUnit\Framework\TestCase;

#[CoversClass( FieldProcessor::class )]
#[UsesClass( SettingsField::class )]
#[UsesClass( OptionsResolver::class )]
#[UsesClass( CustomFieldType::class )]
#[UsesClass( Success::class )]
#[UsesClass( Failure::class )]
#[UsesClass( FieldProcessingError::class )]
#[UsesClass( FieldProcessingErrorReason::class )]
#[UsesFunction( 'DeepWebSolutions\Framework\Settings\Schema\is_valid_identifier' )]
final class FieldProcessorTest extends TestCase {
	public function test_an_unknown_field_type_throws(): void {
		$this->expectException( UnknownFieldTypeException::class );

		( new FieldProcessor() )->process( $this->field( 'f', 'bogus' ), array( 'f' => 'x' ) );
	}

	public function test_an_unregistered_custom_type_still_throws(): void {
		// The registry is an allowlist: a type absent from it stays loud, so a typo is caught, not silently persisted.
		$processor = new FieldProcessor(
			custom_types: array( 'color_picker' => $this->custom_type( 'color_picker' ) ),
		);

		$this->expectException( UnknownFieldTypeException::class );

		$processor->process( $this->field( 'f', 'bogus' ), array( 'f' => 'x' ) );
	}

	public function test_a_registered_custom_type_applies_the_fields_sanitize(): void {
		$processor = new FieldProcessor(
			custom_types: array( 'color_picker' => $this->custom_type( 'color_picker' ) ),
		);
		$field     = new SettingsField( id: 'shade', type: 'color_picker', label: 'Shade', sanitize: 'trim' );

		self::assertSame( '#abc', $processor->process( $field, array( 'shade' => '  #abc  ' ) ) );
	}

	public function test_a_registered_custom_type_with_no_submission_returns_the_default(): void {
		// A custom type follows the established custom-save shape (ProductData): an absent submission falls
		// back to the field's default, not the scalar-empty false the taxonomy branch coerces to.
		$processor = new FieldProcessor(
			custom_types: array( 'color_picker' => $this->custom_type( 'color_picker' ) ),
		);
		$field     = new SettingsField( id: 'shade', type: 'color_picker', label: 'Shade', default_value: '#000' );

		self::assertSame( '#000', $processor->process( $field, array() ) );
	}

	public function test_a_registered_custom_type_failing_validation_returns_the_default(): void {
		$processor = new FieldProcessor(
			custom_types: array( 'color_picker' => $this->custom_type( 'color_picker' ) ),
		);
		$field     = new SettingsField(
			id: 'shade',
			type: 'color_picker',
			label: 'Shade',
			default_value: '#fallback',
			validate: static fn ( mixed $value ): bool => false,
		);

		self::assertSame( '#fallback', $processor->process( $field, array( 'shade' => '#abc' ) ) );
	}

	public function test_a_registered_custom_type_applies_sanitize_before_validate(): void {
		$processor = new FieldProcessor(
			custom_types: array( 'color_picker' => $this->custom_type( 'color_picker' ) ),
		);
		// The validator accepts only the trimmed value; were it run before sanitize, it would reject to the default.
		$field = new SettingsField(
			id: 'shade',
			type: 'color_picker',
			label: 'Shade',
			default_value: '#fallback',
			sanitize: 'trim',
			validate: static fn ( mixed $value ): bool => '#abc' === $value,
		);

		self::assertSame( '#abc', $processor->process( $field, array( 'shade' => '  #abc  ' ) ) );
	}

	public function test_a_registered_custom_type_with_a_non_scalar_submission_returns_the_default(): void {
		// A tampered array submission (shade[]=x) to a scalar custom field whose sanitize is a scalar callable
		// (trim) must coerce to the default, not fatal the sanitizer with a TypeError.
		$processor = new FieldProcessor(
			custom_types: array( 'color_picker' => $this->custom_type( 'color_picker' ) ),
		);
		$field     = new SettingsField( id: 'shade', type: 'color_picker', label: 'Shade', default_value: '#000', sanitize: 'trim' );

		self::assertSame( '#000', $processor->process( $field, array( 'shade' => array( 'x' ) ) ) );
	}

	private function custom_type( string $type ): CustomFieldType {
		return new CustomFieldType(
			type: $type,
			render: static fn ( SettingsField $field, mixed $value, string $name ): string => '',
		);
	}

	public function test_an_absent_checkbox_coerces_to_false(): void {
		$result = ( new FieldProcessor() )->process( $this->field( 'agree', 'checkbox' ), array() );

		self::assertFalse( $result );
	}

	public function test_an_absent_multiselect_coerces_to_an_empty_array(): void {
		$result = ( new FieldProcessor() )->process( $this->field( 'tags', 'multiselect' ), array() );

		self::assertSame( array(), $result );
	}

	public function test_an_absent_scalar_field_coerces_to_false_never_null(): void {
		$result = ( new FieldProcessor() )->process( $this->field( 'name', 'text' ), array() );

		self::assertFalse( $result );
	}

	public function test_a_present_value_passes_through_without_sanitize_or_validate(): void {
		$result = ( new FieldProcessor() )->process( $this->field( 'name', 'text' ), array( 'name' => 'Ada' ) );

		self::assertSame( 'Ada', $result );
	}

	public function test_a_non_scalar_submission_to_a_scalar_field_coerces_to_false(): void {
		// A tampered array submission (name[]=x) to a scalar field must not reach the sanitizer or persist.
		$result = ( new FieldProcessor() )->process( $this->field( 'name', 'text' ), array( 'name' => array( 'x' ) ) );

		self::assertFalse( $result );
	}

	public function test_sanitize_runs_before_validate(): void {
		// The validator accepts only the trimmed value; were it run before sanitize, it would reject.
		$field = new SettingsField(
			id: 'name',
			type: 'text',
			label: 'N',
			sanitize: 'trim',
			validate: static fn ( mixed $value ): bool => 'x' === $value,
		);

		$result = ( new FieldProcessor() )->process( $field, array( 'name' => '  x  ' ) );

		self::assertSame( 'x', $result );
	}

	public function test_a_valid_value_passes_when_validate_returns_true(): void {
		$field = new SettingsField(
			id: 'name',
			type: 'text',
			label: 'N',
			validate: static fn ( mixed $value ): bool => true,
		);

		$result = ( new FieldProcessor() )->process( $field, array( 'name' => 'ok' ) );

		self::assertSame( 'ok', $result );
	}

	public function test_a_failed_validation_rejects_a_scalar_to_false(): void {
		$field = new SettingsField(
			id: 'name',
			type: 'text',
			label: 'N',
			validate: static fn ( mixed $value ): bool => false,
		);

		$result = ( new FieldProcessor() )->process( $field, array( 'name' => 'anything' ) );

		self::assertFalse( $result );
	}

	public function test_a_failed_validation_rejects_a_multiselect_to_an_empty_array(): void {
		$field = new SettingsField(
			id: 'tags',
			type: 'multiselect',
			label: 'T',
			validate: static fn ( mixed $value ): bool => false,
			options: array( 'a' => 'A' ),
		);

		$result = ( new FieldProcessor() )->process( $field, array( 'tags' => array( 'a' ) ) );

		self::assertSame( array(), $result );
	}

	public function test_a_select_value_in_the_options_is_accepted(): void {
		$field = new SettingsField(
			id: 'color',
			type: 'select',
			label: 'C',
			options: array(
				'red'  => 'Red',
				'blue' => 'Blue',
			),
		);

		self::assertSame( 'red', ( new FieldProcessor() )->process( $field, array( 'color' => 'red' ) ) );
	}

	public function test_a_select_value_outside_the_options_is_rejected_to_false(): void {
		$field = new SettingsField( id: 'color', type: 'select', label: 'C', options: array( 'red' => 'Red' ) );

		self::assertFalse( ( new FieldProcessor() )->process( $field, array( 'color' => 'green' ) ) );
	}

	public function test_a_radio_value_is_validated_against_the_options(): void {
		$field = new SettingsField(
			id: 'size',
			type: 'radio',
			label: 'S',
			options: array(
				's' => 'Small',
				'l' => 'Large',
			),
		);

		self::assertSame( 'l', ( new FieldProcessor() )->process( $field, array( 'size' => 'l' ) ) );
		self::assertFalse( ( new FieldProcessor() )->process( $field, array( 'size' => 'xl' ) ) );
	}

	public function test_a_multiselect_filters_out_values_outside_the_options(): void {
		$field = new SettingsField(
			id: 'tags',
			type: 'multiselect',
			label: 'T',
			options: array(
				'a' => 'A',
				'b' => 'B',
				'c' => 'C',
			),
		);

		$result = ( new FieldProcessor() )->process( $field, array( 'tags' => array( 'a', 'x', 'c' ) ) );

		self::assertSame( array( 'a', 'c' ), $result );
	}

	public function test_a_multiselect_with_no_valid_values_yields_an_empty_array(): void {
		$field = new SettingsField( id: 'tags', type: 'multiselect', label: 'T', options: array( 'a' => 'A' ) );

		$result = ( new FieldProcessor() )->process( $field, array( 'tags' => array( 'x', 'y' ) ) );

		self::assertSame( array(), $result );
	}

	public function test_choice_membership_uses_dynamically_resolved_options(): void {
		$field = new SettingsField(
			id: 'color',
			type: 'select',
			label: 'C',
			options: static fn (): array => array( 'red' => 'Red' ),
		);

		self::assertSame( 'red', ( new FieldProcessor() )->process( $field, array( 'color' => 'red' ) ) );
		self::assertFalse( ( new FieldProcessor() )->process( $field, array( 'color' => 'green' ) ) );
	}

	public function test_an_integer_select_value_matching_an_integer_option_is_accepted(): void {
		$field = new SettingsField(
			id: 'year',
			type: 'select',
			label: 'Y',
			options: array(
				1 => 'One',
				2 => 'Two',
			),
		);

		self::assertSame( 1, ( new FieldProcessor() )->process( $field, array( 'year' => 1 ) ) );
	}

	public function test_a_non_scalar_select_value_is_rejected_without_error(): void {
		$field = new SettingsField( id: 'color', type: 'select', label: 'C', options: array( 'red' => 'Red' ) );

		// A tampered submission (color[]=red) arrives as an array; it must be rejected, not crash array_key_exists.
		self::assertFalse( ( new FieldProcessor() )->process( $field, array( 'color' => array( 'red' ) ) ) );
	}

	public function test_a_boolean_select_value_is_rejected_rather_than_coerced_to_a_key(): void {
		$field = new SettingsField( id: 'choice', type: 'select', label: 'C', options: array( 1 => 'One' ) );

		// A bool is scalar (so it clears the shape guard) but is not a valid option key; it must be rejected,
		// not coerced to the matching integer key 1.
		self::assertFalse( ( new FieldProcessor() )->process( $field, array( 'choice' => true ) ) );
	}

	public function test_a_multiselect_keeps_integer_values_matching_integer_options(): void {
		$field = new SettingsField(
			id: 'years',
			type: 'multiselect',
			label: 'Y',
			options: array(
				1 => 'One',
				2 => 'Two',
			),
		);

		self::assertSame( array( 1, 2 ), ( new FieldProcessor() )->process( $field, array( 'years' => array( 1, 2 ) ) ) );
	}

	public function test_a_multiselect_drops_non_scalar_members(): void {
		$field = new SettingsField( id: 'tags', type: 'multiselect', label: 'T', options: array( 'a' => 'A' ) );

		$result = ( new FieldProcessor() )->process( $field, array( 'tags' => array( 'a', array( 'nested' ) ) ) );

		self::assertSame( array( 'a' ), $result );
	}

	public function test_a_null_sanitize_result_is_coerced_to_empty_never_null(): void {
		$field = new SettingsField(
			id: 'name',
			type: 'text',
			label: 'N',
			sanitize: static fn ( mixed $value ): mixed => null,
		);

		$result = ( new FieldProcessor() )->process( $field, array( 'name' => 'x' ) );

		self::assertFalse( $result );
	}

	public function test_a_type_sanitizer_applies_when_the_field_has_no_sanitizer(): void {
		$processor = new FieldProcessor(
			type_sanitizers: array( 'text' => static fn ( mixed $value ): string => \strtoupper( (string) $value ) ),
		);

		self::assertSame( 'ADA', $processor->process( $this->field( 'name', 'text' ), array( 'name' => 'ada' ) ) );
	}

	public function test_a_field_sanitizer_takes_precedence_over_the_type_sanitizer(): void {
		$processor = new FieldProcessor(
			type_sanitizers: array( 'text' => static fn ( mixed $value ): string => \strtoupper( (string) $value ) ),
		);
		$field     = new SettingsField(
			id: 'name',
			type: 'text',
			label: 'N',
			sanitize: static fn ( mixed $value ): string => \strtolower( (string) $value ),
		);

		self::assertSame( 'ada', $processor->process( $field, array( 'name' => 'ADA' ) ) );
	}

	public function test_a_type_without_a_registered_sanitizer_passes_the_value_through(): void {
		$processor = new FieldProcessor(
			type_sanitizers: array( 'text' => static fn ( mixed $value ): string => \strtoupper( (string) $value ) ),
		);

		self::assertSame( '42', $processor->process( $this->field( 'qty', 'number' ), array( 'qty' => '42' ) ) );
	}

	public function test_process_or_reject_returns_success_carrying_the_empty_for_an_absent_field(): void {
		$result = ( new FieldProcessor() )->process_or_reject( $this->field( 'name', 'text' ), array() );

		self::assertInstanceOf( Success::class, $result );
		self::assertFalse( $result->value );
	}

	public function test_process_or_reject_returns_success_carrying_a_valid_value(): void {
		$result = ( new FieldProcessor() )->process_or_reject( $this->field( 'name', 'text' ), array( 'name' => 'Ada' ) );

		self::assertInstanceOf( Success::class, $result );
		self::assertSame( 'Ada', $result->value );
	}

	public function test_process_or_reject_returns_success_carrying_empty_choice_values(): void {
		$select = new SettingsField( id: 'color', type: 'select', label: 'C', options: array( 'red' => 'Red' ) );
		$radio  = new SettingsField( id: 'size', type: 'radio', label: 'S', options: array( 's' => 'Small' ) );

		$select_result = ( new FieldProcessor() )->process_or_reject( $select, array( 'color' => false ) );
		$radio_result  = ( new FieldProcessor() )->process_or_reject( $radio, array( 'size' => false ) );

		self::assertInstanceOf( Success::class, $select_result );
		self::assertFalse( $select_result->value );
		self::assertInstanceOf( Success::class, $radio_result );
		self::assertFalse( $radio_result->value );
	}

	public function test_process_or_reject_returns_success_carrying_empty_scalar_before_type_sanitize(): void {
		$processor = new FieldProcessor(
			type_sanitizers: array( 'text' => static fn ( mixed $value ): string => 'MANGLED' ),
		);

		$result = $processor->process_or_reject( $this->field( 'name', 'text' ), array( 'name' => false ) );

		self::assertInstanceOf( Success::class, $result );
		self::assertFalse( $result->value );
	}

	public function test_process_or_reject_fails_for_a_non_scalar_submission(): void {
		$result = ( new FieldProcessor() )->process_or_reject( $this->field( 'name', 'text' ), array( 'name' => array( 'x' ) ) );

		self::assertInstanceOf( Failure::class, $result );
		self::assertInstanceOf( FieldProcessingError::class, $result->error );
		self::assertSame( 'name', $result->error->field_id );
		self::assertSame( FieldProcessingErrorReason::UnexpectedShape, $result->error->reason );
	}

	public function test_process_or_reject_fails_for_a_value_outside_the_options(): void {
		$field  = new SettingsField( id: 'color', type: 'select', label: 'C', options: array( 'red' => 'Red' ) );
		$result = ( new FieldProcessor() )->process_or_reject( $field, array( 'color' => 'green' ) );

		self::assertInstanceOf( Failure::class, $result );
		self::assertInstanceOf( FieldProcessingError::class, $result->error );
		self::assertSame( 'color', $result->error->field_id );
		self::assertSame( FieldProcessingErrorReason::NotAnOption, $result->error->reason );
	}

	public function test_process_or_reject_fails_for_a_value_that_fails_validation(): void {
		$field  = new SettingsField( id: 'name', type: 'text', label: 'N', validate: static fn ( mixed $value ): bool => false );
		$result = ( new FieldProcessor() )->process_or_reject( $field, array( 'name' => 'anything' ) );

		self::assertInstanceOf( Failure::class, $result );
		self::assertInstanceOf( FieldProcessingError::class, $result->error );
		self::assertSame( FieldProcessingErrorReason::FailedValidation, $result->error->reason );
	}

	public function test_process_or_reject_filters_a_multiselect_to_valid_options_as_a_success(): void {
		$field  = new SettingsField( id: 'tags', type: 'multiselect', label: 'T', options: array( 'a' => 'A', 'b' => 'B' ) );
		$result = ( new FieldProcessor() )->process_or_reject( $field, array( 'tags' => array( 'a', 'x', 'b' ) ) );

		self::assertInstanceOf( Success::class, $result );
		self::assertSame( array( 'a', 'b' ), $result->value );
	}

	public function test_process_or_reject_returns_success_for_a_custom_type(): void {
		$processor = new FieldProcessor(
			custom_types: array( 'color_picker' => $this->custom_type( 'color_picker' ) ),
		);
		$field     = new SettingsField( id: 'shade', type: 'color_picker', label: 'Shade', default_value: '#000' );

		$result = $processor->process_or_reject( $field, array() );

		self::assertInstanceOf( Success::class, $result );
		self::assertSame( '#000', $result->value );
	}

	public function test_process_or_reject_fails_for_a_custom_type_that_fails_validation(): void {
		$processor = new FieldProcessor(
			custom_types: array( 'color_picker' => $this->custom_type( 'color_picker' ) ),
		);
		$field     = new SettingsField(
			id: 'shade',
			type: 'color_picker',
			label: 'Shade',
			default_value: '#000',
			validate: static fn ( mixed $value ): bool => false,
		);

		$result = $processor->process_or_reject( $field, array( 'shade' => '#abc' ) );

		self::assertInstanceOf( Failure::class, $result );
		self::assertInstanceOf( FieldProcessingError::class, $result->error );
		self::assertSame( FieldProcessingErrorReason::FailedValidation, $result->error->reason );
	}

	public function test_process_or_reject_fails_for_a_non_scalar_custom_type_submission(): void {
		$processor = new FieldProcessor(
			custom_types: array( 'color_picker' => $this->custom_type( 'color_picker' ) ),
		);
		$field     = new SettingsField( id: 'shade', type: 'color_picker', label: 'Shade', default_value: '#000' );

		$result = $processor->process_or_reject( $field, array( 'shade' => array( 'x' ) ) );

		self::assertInstanceOf( Failure::class, $result );
		self::assertInstanceOf( FieldProcessingError::class, $result->error );
		self::assertSame( FieldProcessingErrorReason::UnexpectedShape, $result->error->reason );
	}

	private function field( string $id, string $type ): SettingsField {
		return new SettingsField( id: $id, type: $type, label: $id );
	}
}
