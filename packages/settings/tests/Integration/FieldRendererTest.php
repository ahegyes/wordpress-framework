<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Tests\Integration;

use DeepWebSolutions\Framework\Settings\Schema\Exceptions\UnknownFieldTypeException;
use DeepWebSolutions\Framework\Settings\Schema\Field\FieldRenderer;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\CustomFieldType;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsField;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( FieldRenderer::class )]
#[UsesClass( SettingsField::class )]
#[UsesClass( CustomFieldType::class )]
final class FieldRendererTest extends TestCase {
	public function test_renders_a_text_input_with_the_value_bound(): void {
		$html = ( new FieldRenderer() )->render( $this->field( 'name', 'text' ), 'Ada', 'opt[name]' );

		self::assertStringContainsString( 'type="text"', $html );
		self::assertStringContainsString( 'name="opt[name]"', $html );
		self::assertStringContainsString( 'value="Ada"', $html );
	}

	public function test_renders_number_email_and_url_input_types(): void {
		$renderer = new FieldRenderer();

		self::assertStringContainsString( 'type="number"', $renderer->render( $this->field( 'n', 'number' ), 1, 'n' ) );
		self::assertStringContainsString( 'type="email"', $renderer->render( $this->field( 'e', 'email' ), '', 'e' ) );
		self::assertStringContainsString( 'type="url"', $renderer->render( $this->field( 'u', 'url' ), '', 'u' ) );
	}

	public function test_escapes_the_bound_value(): void {
		$html = ( new FieldRenderer() )->render( $this->field( 'x', 'text' ), '"><script>alert(1)</script>', 'x' );

		self::assertStringNotContainsString( '<script>', $html );
	}

	public function test_renders_a_textarea_with_its_content(): void {
		$html = ( new FieldRenderer() )->render( $this->field( 'bio', 'textarea' ), 'hello world', 'bio' );

		self::assertStringContainsString( '<textarea', $html );
		self::assertStringContainsString( 'hello world', $html );
	}

	public function test_renders_a_checked_checkbox_for_a_truthy_value(): void {
		$html = ( new FieldRenderer() )->render( $this->field( 'agree', 'checkbox' ), true, 'agree' );

		self::assertStringContainsString( 'type="checkbox"', $html );
		self::assertStringContainsString( 'checked', $html );
	}

	public function test_renders_an_unchecked_checkbox_for_a_falsy_value(): void {
		$html = ( new FieldRenderer() )->render( $this->field( 'agree', 'checkbox' ), false, 'agree' );

		self::assertStringContainsString( 'type="checkbox"', $html );
		self::assertStringNotContainsString( 'checked', $html );
	}

	public function test_renders_a_select_with_the_current_option_selected(): void {
		$field = new SettingsField(
			id: 'color',
			type: 'select',
			label: 'C',
			options: array(
				'red'  => 'Red',
				'blue' => 'Blue',
			),
		);

		$html = ( new FieldRenderer() )->render( $field, 'blue', 'color' );

		self::assertStringContainsString( '<select', $html );
		self::assertMatchesRegularExpression( '/<option value="blue"[^>]*selected/', $html );
		self::assertDoesNotMatchRegularExpression( '/<option value="red"[^>]*selected/', $html );
	}

	public function test_renders_a_select_with_dynamically_resolved_options(): void {
		$field = new SettingsField(
			id: 'color',
			type: 'select',
			label: 'C',
			options: static fn (): array => array( 'red' => 'Red' ),
		);

		$html = ( new FieldRenderer() )->render( $field, 'red', 'color' );

		self::assertStringContainsString( 'value="red"', $html );
		self::assertStringContainsString( 'Red', $html );
	}

	public function test_renders_a_multiselect_binding_selected_values(): void {
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

		$html = ( new FieldRenderer() )->render( $field, array( 'a', 'c' ), 'tags' );

		self::assertStringContainsString( 'multiple', $html );
		self::assertStringContainsString( 'name="tags[]"', $html );
		self::assertMatchesRegularExpression( '/<option value="a"[^>]*selected/', $html );
		self::assertMatchesRegularExpression( '/<option value="c"[^>]*selected/', $html );
		self::assertDoesNotMatchRegularExpression( '/<option value="b"[^>]*selected/', $html );
	}

	public function test_renders_radio_inputs_with_the_current_value_checked(): void {
		$field = new SettingsField(
			id: 'size',
			type: 'radio',
			label: 'S',
			options: array(
				's' => 'Small',
				'l' => 'Large',
			),
		);

		$html = ( new FieldRenderer() )->render( $field, 'l', 'size' );

		self::assertStringContainsString( 'type="radio"', $html );
		self::assertMatchesRegularExpression( '/value="l"[^>]*checked/', $html );
	}

	public function test_passes_through_extra_html_attributes(): void {
		$field = new SettingsField(
			id: 'x',
			type: 'text',
			label: 'X',
			attributes: array(
				'class'       => 'widefat',
				'placeholder' => 'type here',
			),
		);

		$html = ( new FieldRenderer() )->render( $field, '', 'x' );

		self::assertStringContainsString( 'class="widefat"', $html );
		self::assertStringContainsString( 'placeholder="type here"', $html );
	}

	public function test_does_not_render_event_handler_attributes(): void {
		$field = new SettingsField(
			id: 'x',
			type: 'text',
			label: 'X',
			attributes: array(
				'class'   => 'widefat',
				'onfocus' => 'alert(1)',
				'onclick' => 'steal()',
			),
		);

		$html = ( new FieldRenderer() )->render( $field, '', 'x' );

		self::assertStringContainsString( 'class="widefat"', $html );
		self::assertStringNotContainsString( 'onfocus', $html );
		self::assertStringNotContainsString( 'onclick', $html );
	}

	public function test_drops_attribute_keys_with_invalid_characters(): void {
		$field = new SettingsField(
			id: 'x',
			type: 'text',
			label: 'X',
			attributes: array( 'autofocus onfocus' => 'alert(1)' ),
		);

		$html = ( new FieldRenderer() )->render( $field, '', 'x' );

		self::assertStringNotContainsString( 'onfocus', $html );
		self::assertStringNotContainsString( 'autofocus', $html );
	}

	public function test_escapes_malicious_select_option_labels_and_values(): void {
		$field = new SettingsField(
			id: 'color',
			type: 'select',
			label: 'C',
			options: array( '"><script>alert(1)</script>' => '<script>alert(2)</script>' ),
		);

		$html = ( new FieldRenderer() )->render( $field, '', 'color' );

		self::assertStringNotContainsString( '<script>', $html );
	}

	public function test_escapes_malicious_radio_labels(): void {
		$field = new SettingsField(
			id: 'size',
			type: 'radio',
			label: 'S',
			options: array( 's' => '<script>alert(1)</script>' ),
		);

		$html = ( new FieldRenderer() )->render( $field, '', 'size' );

		self::assertStringNotContainsString( '<script>', $html );
	}

	public function test_escapes_malicious_textarea_content(): void {
		$html = ( new FieldRenderer() )->render(
			$this->field( 'bio', 'textarea' ),
			'</textarea><script>alert(1)</script>',
			'bio',
		);

		self::assertStringNotContainsString( '<script>', $html );
	}

	public function test_escapes_the_name_attribute(): void {
		$html = ( new FieldRenderer() )->render( $this->field( 'x', 'text' ), '', 'opt["><script>' );

		self::assertStringNotContainsString( '<script>', $html );
	}

	public function test_an_unknown_type_throws(): void {
		$this->expectException( UnknownFieldTypeException::class );

		( new FieldRenderer() )->render( $this->field( 'x', 'bogus' ), '', 'x' );
	}

	public function test_an_unregistered_custom_type_still_throws(): void {
		// A registry registered for a different type does not make every unknown token valid.
		$renderer = new FieldRenderer(
			custom_types: array(
				'color_picker' => new CustomFieldType(
					type: 'color_picker',
					render: static fn ( SettingsField $field, mixed $value, string $name ): string => '',
				),
			),
		);

		$this->expectException( UnknownFieldTypeException::class );

		$renderer->render( $this->field( 'x', 'bogus' ), '', 'x' );
	}

	public function test_a_registered_custom_renderer_is_invoked_with_the_field_value_and_name(): void {
		$renderer = new FieldRenderer(
			custom_types: array(
				'color_picker' => new CustomFieldType(
					type: 'color_picker',
					render: static fn ( SettingsField $field, mixed $value, string $name ): string => \sprintf(
						'<input class="dws-color" name="%s" value="%s" />',
						\esc_attr( $name ),
						\esc_attr( (string) $value ),
					),
				),
			),
		);

		$html = $renderer->render( $this->field( 'shade', 'color_picker' ), '#abc', 'opt[shade]' );

		self::assertStringContainsString( 'class="dws-color"', $html );
		self::assertStringContainsString( 'name="opt[shade]"', $html );
		self::assertStringContainsString( 'value="#abc"', $html );
	}

	public function test_a_custom_renderer_gets_the_field_description_appended(): void {
		$field    = new SettingsField( id: 'shade', type: 'color_picker', label: 'Shade', description: 'Pick a shade.' );
		$renderer = new FieldRenderer(
			custom_types: array(
				'color_picker' => new CustomFieldType(
					type: 'color_picker',
					render: static fn ( SettingsField $f, mixed $value, string $name ): string => '<input id="control" />',
				),
			),
		);

		$html = $renderer->render( $field, '', 'shade' );

		self::assertStringContainsString( 'Pick a shade.', $html );
		self::assertStringContainsString( 'class="description"', $html );
		// The description follows the custom control, parity with the built-in types.
		self::assertGreaterThan( \strpos( $html, '<input' ), \strpos( $html, 'Pick a shade.' ) );
	}

	public function test_renders_the_field_description_after_the_control(): void {
		$field = new SettingsField( id: 'x', type: 'text', label: 'X', description: 'Helpful hint.' );

		$html = ( new FieldRenderer() )->render( $field, '', 'x' );

		self::assertStringContainsString( 'Helpful hint.', $html );
		self::assertStringContainsString( 'class="description"', $html );
		// The description follows the control, not precedes it.
		self::assertGreaterThan( \strpos( $html, '<input' ), \strpos( $html, 'Helpful hint.' ) );
	}

	public function test_omits_the_description_when_none_is_set(): void {
		$html = ( new FieldRenderer() )->render( $this->field( 'x', 'text' ), '', 'x' );

		self::assertStringNotContainsString( 'class="description"', $html );
	}

	public function test_escapes_the_field_description(): void {
		$field = new SettingsField( id: 'x', type: 'text', label: 'X', description: '"><script>alert(1)</script>' );

		$html = ( new FieldRenderer() )->render( $field, '', 'x' );

		self::assertStringNotContainsString( '<script>', $html );
	}

	public function test_a_text_input_carries_the_id_derived_from_its_name(): void {
		$html = ( new FieldRenderer() )->render( $this->field( 'name', 'text' ), 'Ada', 'opt[name]' );

		self::assertStringContainsString( 'id="opt.name"', $html );
		self::assertStringContainsString( 'name="opt[name]"', $html );
	}

	public function test_each_single_control_type_carries_the_derived_id(): void {
		$renderer = new FieldRenderer();

		self::assertStringContainsString( 'id="o.n"', $renderer->render( $this->field( 'n', 'number' ), 1, 'o[n]' ) );
		self::assertStringContainsString( 'id="o.e"', $renderer->render( $this->field( 'e', 'email' ), '', 'o[e]' ) );
		self::assertStringContainsString( 'id="o.u"', $renderer->render( $this->field( 'u', 'url' ), '', 'o[u]' ) );
		self::assertStringContainsString( 'id="o.bio"', $renderer->render( $this->field( 'bio', 'textarea' ), '', 'o[bio]' ) );
		self::assertStringContainsString( 'id="o.agree"', $renderer->render( $this->field( 'agree', 'checkbox' ), true, 'o[agree]' ) );
	}

	public function test_a_select_carries_the_derived_id(): void {
		$field = new SettingsField( id: 'color', type: 'select', label: 'C', options: array( 'red' => 'Red' ) );

		$html = ( new FieldRenderer() )->render( $field, 'red', 'opt[color]' );

		self::assertStringContainsString( '<select id="opt.color"', $html );
	}

	public function test_a_multiselect_derives_its_id_from_the_base_name(): void {
		$field = new SettingsField( id: 'tags', type: 'multiselect', label: 'T', options: array( 'a' => 'A' ) );

		$html = ( new FieldRenderer() )->render( $field, array(), 'opt[tags]' );

		// The submit name gains the [] suffix, but the DOM id stays the base name's derivation.
		self::assertStringContainsString( 'name="opt[tags][]"', $html );
		self::assertStringContainsString( 'id="opt.tags"', $html );
	}

	public function test_a_radio_group_renders_a_fieldset_with_a_screen_reader_legend(): void {
		$field = new SettingsField(
			id: 'size',
			type: 'radio',
			label: 'Size',
			options: array(
				's' => 'Small',
				'l' => 'Large',
			),
		);

		$html = ( new FieldRenderer() )->render( $field, 's', 'opt[size]' );

		self::assertStringContainsString( '<fieldset id="opt.size"><legend class="screen-reader-text">Size</legend>', $html );
		self::assertStringContainsString( '</fieldset>', $html );
		// Each option stays a label-wrapped radio input, so every radio has its own label.
		self::assertMatchesRegularExpression( '/<label><input type="radio"[^>]*value="s"[^>]*\/> Small<\/label>/', $html );
		self::assertMatchesRegularExpression( '/<label><input type="radio"[^>]*value="l"[^>]*\/> Large<\/label>/', $html );
	}

	public function test_a_radio_legend_escapes_the_field_label(): void {
		$field = new SettingsField(
			id: 'size',
			type: 'radio',
			label: '<script>alert(1)</script>',
			options: array( 's' => 'Small' ),
		);

		$html = ( new FieldRenderer() )->render( $field, '', 'size' );

		self::assertStringNotContainsString( '<script>', $html );
	}

	public function test_a_name_outside_the_id_charset_emits_no_id(): void {
		$html = ( new FieldRenderer() )->render( $this->field( 'x', 'text' ), '', 'OPT[x]' );

		self::assertStringNotContainsString( ' id="', $html );
	}

	public function test_a_description_is_associated_via_aria_describedby(): void {
		$field = new SettingsField( id: 'x', type: 'text', label: 'X', description: 'Helpful hint.' );

		$html = ( new FieldRenderer() )->render( $field, '', 'opt[x]' );

		self::assertStringContainsString( 'aria-describedby="opt.x..description"', $html );
		self::assertStringContainsString( '<p class="description" id="opt.x..description">Helpful hint.</p>', $html );
	}

	public function test_a_radio_fieldset_carries_the_aria_describedby(): void {
		$field = new SettingsField(
			id: 'size',
			type: 'radio',
			label: 'Size',
			description: 'Pick one.',
			options: array( 's' => 'Small' ),
		);

		$html = ( new FieldRenderer() )->render( $field, '', 'opt[size]' );

		// The description applies to the whole group, so the reference sits on the fieldset, not a radio.
		self::assertStringContainsString( '<fieldset id="opt.size" aria-describedby="opt.size..description">', $html );
		self::assertStringContainsString( '<p class="description" id="opt.size..description">Pick one.</p>', $html );
		self::assertStringNotContainsString( '<input type="radio" aria-describedby', $html );
	}

	public function test_no_aria_describedby_without_a_description(): void {
		$html = ( new FieldRenderer() )->render( $this->field( 'x', 'text' ), '', 'opt[x]' );

		self::assertStringNotContainsString( 'aria-describedby', $html );
	}

	public function test_no_dangling_aria_describedby_when_no_id_derives(): void {
		$field = new SettingsField( id: 'x', type: 'text', label: 'X', description: 'Helpful hint.' );

		$html = ( new FieldRenderer() )->render( $field, '', 'OPT[x]' );

		// No control id derived: neither the reference nor the target id is emitted, only the plain paragraph.
		self::assertStringNotContainsString( 'aria-describedby', $html );
		self::assertStringContainsString( '<p class="description">Helpful hint.</p>', $html );
	}

	public function test_a_custom_type_description_stays_unassociated(): void {
		$field    = new SettingsField( id: 'shade', type: 'color_picker', label: 'Shade', description: 'Pick a shade.' );
		$renderer = new FieldRenderer(
			custom_types: array(
				'color_picker' => new CustomFieldType(
					type: 'color_picker',
					render: static fn ( SettingsField $f, mixed $value, string $name ): string => '<input class="dws-color" />',
				),
			),
		);

		$html = $renderer->render( $field, '', 'opt[shade]' );

		// The custom closure owns the control markup, so no id is guaranteed and no reference is emitted.
		self::assertStringNotContainsString( 'aria-describedby', $html );
		self::assertStringContainsString( '<p class="description">Pick a shade.</p>', $html );
	}

	public function test_a_descriptor_id_attribute_replaces_the_derived_id(): void {
		$field = new SettingsField( id: 'x', type: 'text', label: 'X', description: 'Hint.', attributes: array( 'id' => 'legacy-id' ) );

		$html = ( new FieldRenderer() )->render( $field, '', 'opt[x]' );

		// The consumer's id is the control id: the description id derives from it, the derived id is
		// dropped, and the consumed attribute is not passed through the attribute tail a second time.
		self::assertStringContainsString( 'aria-describedby="legacy-id..description"', $html );
		self::assertStringContainsString( '<p class="description" id="legacy-id..description">Hint.</p>', $html );
		self::assertStringNotContainsString( 'id="opt.x"', $html );
		self::assertSame( 1, \substr_count( $html, ' id="legacy-id"' ) );
	}

	public function test_a_consumer_describedby_merges_with_the_generated_description_id(): void {
		$field = new SettingsField( id: 'x', type: 'text', label: 'X', description: 'Hint.', attributes: array( 'aria-describedby' => 'external-note' ) );

		$html = ( new FieldRenderer() )->render( $field, '', 'opt[x]' );

		// One IDREF attribute: consumer tokens first, the generated description id appended.
		self::assertStringContainsString( 'aria-describedby="external-note opt.x..description"', $html );
		self::assertSame( 1, \substr_count( $html, 'aria-describedby' ) );
	}

	public function test_a_consumer_describedby_passes_through_without_a_description(): void {
		$field = new SettingsField( id: 'x', type: 'text', label: 'X', attributes: array( 'aria-describedby' => 'external-note' ) );

		$html = ( new FieldRenderer() )->render( $field, '', 'opt[x]' );

		self::assertStringContainsString( 'aria-describedby="external-note"', $html );
		self::assertSame( 1, \substr_count( $html, 'aria-describedby' ) );
	}

	public function test_a_consumer_describedby_listing_the_description_id_is_not_duplicated(): void {
		$field = new SettingsField( id: 'x', type: 'text', label: 'X', description: 'Hint.', attributes: array( 'aria-describedby' => 'opt.x..description' ) );

		$html = ( new FieldRenderer() )->render( $field, '', 'opt[x]' );

		self::assertStringContainsString( 'aria-describedby="opt.x..description"', $html );
		self::assertSame( 1, \substr_count( $html, 'aria-describedby' ) );
	}

	private function field( string $id, string $type ): SettingsField {
		return new SettingsField( id: $id, type: $type, label: $id );
	}
}
