<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Tests\Unit;

use DeepWebSolutions\Framework\Settings\Schema\Exceptions\DuplicateSettingsFieldException;
use DeepWebSolutions\Framework\Settings\Schema\Exceptions\DuplicateSettingsSectionException;
use DeepWebSolutions\Framework\Settings\Schema\Exceptions\UnsupportedRestExposureException;
use DeepWebSolutions\Framework\Settings\Schema\Field\FieldType;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsField;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsPage;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsSection;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesFunction;
use PHPUnit\Framework\TestCase;

use function DeepWebSolutions\Framework\Settings\Schema\assert_unique_section_and_field_ids;
use function DeepWebSolutions\Framework\Settings\Schema\field_control_id;
use function DeepWebSolutions\Framework\Settings\Schema\filter_field_attributes;
use function DeepWebSolutions\Framework\Settings\Schema\is_checkbox_checked;
use function DeepWebSolutions\Framework\Settings\Schema\normalize_checkbox_value;
use function DeepWebSolutions\Framework\Settings\Schema\resolve_field_control_id;
use function DeepWebSolutions\Framework\Settings\Schema\rest_schema_for_field;
use function DeepWebSolutions\Framework\Settings\Schema\stringify_for_output;
use function DeepWebSolutions\Framework\Settings\Schema\wordpress_field_type_sanitizers;

#[CoversFunction( 'DeepWebSolutions\Framework\Settings\Schema\assert_unique_section_and_field_ids' )]
#[CoversFunction( 'DeepWebSolutions\Framework\Settings\Schema\field_control_id' )]
#[CoversFunction( 'DeepWebSolutions\Framework\Settings\Schema\filter_field_attributes' )]
#[CoversFunction( 'DeepWebSolutions\Framework\Settings\Schema\resolve_field_control_id' )]
#[CoversFunction( 'DeepWebSolutions\Framework\Settings\Schema\is_checkbox_checked' )]
#[CoversFunction( 'DeepWebSolutions\Framework\Settings\Schema\normalize_checkbox_value' )]
#[CoversFunction( 'DeepWebSolutions\Framework\Settings\Schema\rest_schema_for_field' )]
#[CoversFunction( 'DeepWebSolutions\Framework\Settings\Schema\stringify_for_output' )]
#[CoversFunction( 'DeepWebSolutions\Framework\Settings\Schema\wordpress_field_type_sanitizers' )]
#[UsesClass( SettingsField::class )]
#[UsesClass( SettingsSection::class )]
#[UsesClass( SettingsPage::class )]
#[UsesClass( FieldType::class )]
#[UsesClass( DuplicateSettingsSectionException::class )]
#[UsesClass( DuplicateSettingsFieldException::class )]
#[UsesClass( UnsupportedRestExposureException::class )]
#[UsesFunction( 'DeepWebSolutions\Framework\Shared\Identifier\is_valid_identifier' )]
final class SchemaFunctionsTest extends TestCase {
	public function test_keeps_well_formed_non_event_attribute_names(): void {
		$kept = filter_field_attributes(
			array(
				'class'    => 'widefat',
				'data-foo' => 'bar',
				'min'      => '0',
				'step'     => '1',
			),
		);

		self::assertSame(
			array(
				'class'    => 'widefat',
				'data-foo' => 'bar',
				'min'      => '0',
				'step'     => '1',
			),
			$kept,
		);
	}

	public function test_keeps_a_mixed_case_attribute_name(): void {
		self::assertSame(
			array( 'data-Foo' => 'bar' ),
			filter_field_attributes( array( 'data-Foo' => 'bar' ) ),
		);
	}

	#[DataProvider( 'event_handler_names' )]
	public function test_drops_every_on_prefixed_name( string $name ): void {
		self::assertSame(
			array(),
			filter_field_attributes( array( $name => 'evil()' ) ),
		);
	}

	/**
	 * Every name beginning with "on" is dropped by prefix, including ones that are not real handlers
	 * (once, on-call) — the strict prefix block is conservative on purpose.
	 *
	 * @return array<string, array{string}>
	 */
	public static function event_handler_names(): array {
		return array(
			'onclick'       => array( 'onclick' ),
			'onmouseover'   => array( 'onmouseover' ),
			'onfocus'       => array( 'onfocus' ),
			'once'          => array( 'once' ),
			'on-call'       => array( 'on-call' ),
			'mixed-case ON' => array( 'ONload' ),
		);
	}

	#[DataProvider( 'malformed_attribute_names' )]
	public function test_drops_malformed_attribute_names( string $name ): void {
		self::assertSame(
			array(),
			filter_field_attributes( array( $name => 'x' ) ),
		);
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function malformed_attribute_names(): array {
		return array(
			'empty'           => array( '' ),
			'leading digit'   => array( '1bad' ),
			'leading hyphen'  => array( '-bad' ),
			'underscore'      => array( 'data_foo' ),
			'space-separated' => array( 'autofocus onfocus' ),
			'dot'             => array( 'data.foo' ),
		);
	}

	public function test_keeps_valid_drops_invalid_in_one_pass(): void {
		// The dropped names precede the kept one so a skip that fell through to break would drop it too.
		$kept = filter_field_attributes(
			array(
				'onclick' => 'evil()',
				'1bad'    => 'x',
				'min'     => '0',
			),
		);

		self::assertSame( array( 'min' => '0' ), $kept );
	}

	public function test_field_control_id_joins_bracketed_segments_on_a_dot(): void {
		self::assertSame( 'dws-shop-general.color', field_control_id( 'dws-shop-general[color]' ) );
	}

	public function test_field_control_id_passes_a_bare_name_through(): void {
		self::assertSame( 'color', field_control_id( 'color' ) );
	}

	public function test_field_control_id_is_distinct_per_option_name_for_a_shared_field_id(): void {
		// Two sections (or groups) carrying the same field id derive distinct DOM ids, because the
		// option/group name is part of the control name the id derives from.
		self::assertNotSame(
			field_control_id( 'dws-shop-general[color]' ),
			field_control_id( 'dws-shop-advanced[color]' ),
		);
	}

	public function test_field_control_id_is_injective_across_underscore_boundaries(): void {
		// The '.' separator lies outside the identifier charset, so segment boundaries survive the
		// join: names whose segments merely share a flattened spelling cannot collide.
		self::assertSame( 'general.cache_ttl', field_control_id( 'general[cache_ttl]' ) );
		self::assertSame( 'general_cache.ttl', field_control_id( 'general_cache[ttl]' ) );
		self::assertNotSame( field_control_id( 'general[cache_ttl]' ), field_control_id( 'general_cache[ttl]' ) );
	}

	public function test_a_field_id_ending_in_description_cannot_collide_with_a_description_id(): void {
		// The renderer's description-paragraph id is the control id plus '..description' — a doubled
		// dot no derived control id can contain, since ids join non-empty segments on single dots. A
		// field literally named 'x-description' and a nested name ending in a 'description' segment
		// both derive ids distinct from any description-paragraph id.
		self::assertNotSame(
			field_control_id( 'opt[x-description]' ),
			field_control_id( 'opt[x]' ) . '..description',
		);
		self::assertNotSame(
			field_control_id( 'opt[x][description]' ),
			field_control_id( 'opt[x]' ) . '..description',
		);
	}

	#[DataProvider( 'non_derivable_control_names' )]
	public function test_field_control_id_is_empty_for_a_name_outside_the_charset( string $name ): void {
		self::assertSame( '', field_control_id( $name ) );
	}

	/**
	 * A name outside the segment shape — a segment outside the identifier charset, junk after a
	 * bracket, or an empty segment — yields no id at all: the renderer emits no id attribute rather
	 * than an unvetted one.
	 *
	 * @return array<string, array{string}>
	 */
	public static function non_derivable_control_names(): array {
		return array(
			'empty'                  => array( '' ),
			'uppercase'              => array( 'Opt[color]' ),
			'markup breaker'         => array( 'opt["><script>]' ),
			'space'                  => array( 'opt[my field]' ),
			'leading digit'          => array( '1opt[color]' ),
			'junk after bracket'     => array( 'opt[x]y' ),
			'empty trailing segment' => array( 'opt[tags][]' ),
		);
	}

	public function test_resolve_field_control_id_prefers_a_descriptor_id_attribute(): void {
		$field = new SettingsField( id: 'x', type: 'text', label: 'X', attributes: array( 'id' => 'legacy-id' ) );

		self::assertSame( 'legacy-id', resolve_field_control_id( $field, 'opt[x]' ) );
	}

	public function test_resolve_field_control_id_matches_the_id_attribute_case_insensitively(): void {
		// The attribute filter accepts any casing, so the precedence must too.
		$field = new SettingsField( id: 'x', type: 'text', label: 'X', attributes: array( 'ID' => 'LegacyId' ) );

		self::assertSame( 'LegacyId', resolve_field_control_id( $field, 'opt[x]' ) );
	}

	public function test_resolve_field_control_id_falls_back_to_derivation_without_an_id_attribute(): void {
		$field = new SettingsField( id: 'x', type: 'text', label: 'X', attributes: array( 'class' => 'widefat' ) );

		self::assertSame( 'opt.x', resolve_field_control_id( $field, 'opt[x]' ) );
	}

	public function test_resolve_field_control_id_ignores_an_empty_id_attribute(): void {
		$field = new SettingsField( id: 'x', type: 'text', label: 'X', attributes: array( 'id' => '' ) );

		self::assertSame( 'opt.x', resolve_field_control_id( $field, 'opt[x]' ) );
	}

	#[DataProvider( 'checkbox_truth_matrix' )]
	public function test_checkbox_truth_rule( mixed $value, bool $expected ): void {
		self::assertSame( $expected, is_checkbox_checked( $value ) );
	}

	#[DataProvider( 'checkbox_truth_matrix' )]
	public function test_normalize_checkbox_value_returns_the_canonical_yes_no_string( mixed $value, bool $checked ): void {
		self::assertSame( $checked ? 'yes' : 'no', normalize_checkbox_value( $value ) );
	}

	/**
	 * The canonical checkbox truth rule: only true, 1, '1', and 'yes' are checked. Everything
	 * else — 'no', 'off', 'false', arbitrary strings — is unchecked.
	 *
	 * @return array<string, array{mixed, bool}>
	 */
	public static function checkbox_truth_matrix(): array {
		return array(
			'bool true'        => array( true, true ),
			'int 1'            => array( 1, true ),
			'string 1'         => array( '1', true ),
			'string yes'       => array( 'yes', true ),
			'bool false'       => array( false, false ),
			'int 0'            => array( 0, false ),
			'string 0'         => array( '0', false ),
			'string no'        => array( 'no', false ),
			'string off'       => array( 'off', false ),
			'string false'     => array( 'false', false ),
			'string on'        => array( 'on', false ),
			'arbitrary string' => array( 'anything', false ),
			'empty string'     => array( '', false ),
			'null'             => array( null, false ),
			'array'            => array( array( 'yes' ), false ),
			'int 2'            => array( 2, false ),
		);
	}

	public function test_assert_unique_ids_accepts_unique_section_and_field_ids(): void {
		$this->expectNotToPerformAssertions();

		assert_unique_section_and_field_ids(
			new SettingsPage(
				slug: 'dws-test',
				page_title: 'Test',
				menu_title: 'Test',
				capability: 'manage_options',
				sections: array(
					new SettingsSection( 'general', 'General', array( new SettingsField( id: 'a', type: 'text', label: 'A' ) ) ),
					new SettingsSection( 'advanced', 'Advanced', array( new SettingsField( id: 'b', type: 'text', label: 'B' ) ) ),
				),
			),
		);
	}

	public function test_assert_unique_ids_rejects_a_duplicate_section_id(): void {
		$this->expectException( DuplicateSettingsSectionException::class );

		assert_unique_section_and_field_ids(
			new SettingsPage(
				slug: 'dws-test',
				page_title: 'Test',
				menu_title: 'Test',
				capability: 'manage_options',
				sections: array(
					new SettingsSection( 'general', 'General', array( new SettingsField( id: 'a', type: 'text', label: 'A' ) ) ),
					new SettingsSection( 'general', 'Again', array( new SettingsField( id: 'b', type: 'text', label: 'B' ) ) ),
				),
			),
		);
	}

	public function test_assert_unique_ids_rejects_a_duplicate_field_id_across_sections(): void {
		$this->expectException( DuplicateSettingsFieldException::class );

		assert_unique_section_and_field_ids(
			new SettingsPage(
				slug: 'dws-test',
				page_title: 'Test',
				menu_title: 'Test',
				capability: 'manage_options',
				sections: array(
					new SettingsSection( 'general', 'General', array( new SettingsField( id: 'dup', type: 'text', label: 'A' ) ) ),
					new SettingsSection( 'advanced', 'Advanced', array( new SettingsField( id: 'dup', type: 'text', label: 'B' ) ) ),
				),
			),
		);
	}

	public function test_assert_unique_ids_reports_a_duplicate_section_before_a_duplicate_field(): void {
		// A page carrying both a repeated section id and a repeated field id reports the section first.
		$this->expectException( DuplicateSettingsSectionException::class );

		assert_unique_section_and_field_ids(
			new SettingsPage(
				slug: 'dws-test',
				page_title: 'Test',
				menu_title: 'Test',
				capability: 'manage_options',
				sections: array(
					new SettingsSection( 'general', 'General', array( new SettingsField( id: 'dup', type: 'text', label: 'A' ) ) ),
					new SettingsSection( 'general', 'Again', array( new SettingsField( id: 'dup', type: 'text', label: 'B' ) ) ),
				),
			),
		);
	}

	#[DataProvider( 'output_stringification_matrix' )]
	public function test_stringify_for_output_coerces_scalars_and_empties_non_scalars( mixed $value, string $expected ): void {
		self::assertSame( $expected, stringify_for_output( $value ) );
	}

	/**
	 * A scalar coerces through PHP's string cast (so false becomes ''); any non-scalar degrades to the
	 * empty string rather than notice-ing or leaking a serialized representation into output.
	 *
	 * @return array<string, array{mixed, string}>
	 */
	public static function output_stringification_matrix(): array {
		return array(
			'string'       => array( 'label', 'label' ),
			'empty string' => array( '', '' ),
			'int'          => array( 42, '42' ),
			'int zero'     => array( 0, '0' ),
			'float'        => array( 3.5, '3.5' ),
			'bool true'    => array( true, '1' ),
			'bool false'   => array( false, '' ),
			'null'         => array( null, '' ),
			'array'        => array( array( 'x' ), '' ),
			'object'       => array( new \stdClass(), '' ),
		);
	}

	public function test_the_number_type_sanitizer_coerces_numeric_input_and_rejects_the_rest(): void {
		$number = wordpress_field_type_sanitizers()[ FieldType::Number->value ];

		self::assertSame( 42, $number( '42' ) );
		self::assertSame( 3.14, $number( '3.14' ) );
		self::assertSame( '', $number( '0x1A' ) ); // phpcs:ignore PHPCompatibility.Numbers.RemovedHexadecimalNumericStrings.Found -- string test datum, never used numerically.
		self::assertSame( '', $number( 'not a number' ) );
		// An out-of-range exponent coerces to a non-finite float, which is never a settings value.
		self::assertSame( '', $number( '1e309' ) );
	}

	/**
	 * @param array<string, mixed> $expected
	 */
	#[DataProvider( 'rest_field_schemas' )]
	public function test_rest_schema_for_field_admits_a_type_value_and_its_empty( string $type, array $expected ): void {
		self::assertSame( $expected, rest_schema_for_field( new SettingsField( id: 'f', type: $type, label: 'F' ) ) );
	}

	/**
	 * Each built-in field type maps to a JSON-schema type that admits both a stored value of the type and
	 * the uniform empty a section row carries for an unsubmitted field: 'no' for a checkbox, false for
	 * another scalar field, an empty array for the multi-value field.
	 *
	 * @return array<string, array{string, array<string, mixed>}>
	 */
	public static function rest_field_schemas(): array {
		return array(
			'text'        => array( 'text', array( 'type' => array( 'string', 'boolean' ) ) ),
			'email'       => array( 'email', array( 'type' => array( 'string', 'boolean' ) ) ),
			'url'         => array( 'url', array( 'type' => array( 'string', 'boolean' ) ) ),
			'textarea'    => array( 'textarea', array( 'type' => array( 'string', 'boolean' ) ) ),
			'checkbox'    => array( 'checkbox', array( 'type' => array( 'boolean', 'string', 'integer' ) ) ),
			'number'      => array( 'number', array( 'type' => array( 'integer', 'number', 'string', 'boolean' ) ) ),
			'select'      => array( 'select', array( 'type' => array( 'string', 'integer', 'boolean' ) ) ),
			'radio'       => array( 'radio', array( 'type' => array( 'string', 'integer', 'boolean' ) ) ),
			'multiselect' => array(
				'multiselect',
				array(
					'type'  => 'array',
					'items' => array( 'type' => array( 'string', 'integer' ) ),
				),
			),
		);
	}

	public function test_rest_schema_for_field_rejects_a_non_built_in_type(): void {
		// A custom field type has a consumer-defined value domain with no faithful static schema.
		$this->expectException( UnsupportedRestExposureException::class );

		rest_schema_for_field( new SettingsField( id: 'f', type: 'single_select_page', label: 'F' ) );
	}
}
