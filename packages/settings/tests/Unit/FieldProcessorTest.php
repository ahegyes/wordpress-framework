<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Tests\Unit;

use DeepWebSolutions\Framework\Settings\Exceptions\UnknownFieldTypeException;
use DeepWebSolutions\Framework\Settings\FieldProcessor;
use DeepWebSolutions\Framework\Settings\OptionsResolver;
use DeepWebSolutions\Framework\Settings\ValueObjects\SettingsField;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( FieldProcessor::class )]
#[UsesClass( SettingsField::class )]
#[UsesClass( OptionsResolver::class )]
final class FieldProcessorTest extends TestCase {
	public function test_an_unknown_field_type_throws(): void {
		$this->expectException( UnknownFieldTypeException::class );

		( new FieldProcessor() )->process( $this->field( 'f', 'bogus' ), array( 'f' => 'x' ) );
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

	private function field( string $id, string $type ): SettingsField {
		return new SettingsField( id: $id, type: $type, label: $id );
	}
}
