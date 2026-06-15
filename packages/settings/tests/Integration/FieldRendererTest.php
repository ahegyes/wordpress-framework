<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Tests\Integration;

use DeepWebSolutions\Framework\Settings\Exceptions\UnknownFieldTypeException;
use DeepWebSolutions\Framework\Settings\FieldRenderer;
use DeepWebSolutions\Framework\Settings\ValueObjects\SettingsField;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( FieldRenderer::class )]
#[UsesClass( SettingsField::class )]
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

	private function field( string $id, string $type ): SettingsField {
		return new SettingsField( id: $id, type: $type, label: $id );
	}
}
