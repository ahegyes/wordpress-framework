<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Schema;

use DeepWebSolutions\Framework\Settings\Schema\Exceptions\UnknownFieldTypeException;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\CustomFieldType;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsField;

use function DeepWebSolutions\Framework\Settings\Schema\filter_field_attributes;
use function DeepWebSolutions\Framework\Settings\Schema\is_checkbox_checked;

/**
 * Renders a field descriptor to an escaped HTML control with its value bound.
 *
 * The shared field-type render layer for the WordPress and MetaBox settings
 * backends (WooCommerce renders its own). Resolves a field's options through the
 * same {@see OptionsResolver} the processor validates against, so the rendered
 * choices and the accepted values always agree. The caller supplies the control's
 * HTML name; a field type outside the taxonomy and the injected custom-type
 * registry throws.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class FieldRenderer {
	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   OptionsResolver                $resolver     Resolver for choice fields' option sets.
	 * @param   array<string, CustomFieldType> $custom_types Registry of render seams for types outside the taxonomy, keyed by type token.
	 */
	public function __construct(
		protected OptionsResolver $resolver = new OptionsResolver(),
		protected array $custom_types = array(),
	) {}

	// endregion

	// region METHODS

	/**
	 * Renders a field's control as an escaped HTML string.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   SettingsField $field Field to render.
	 * @param   mixed         $value Current value to bind into the control.
	 * @param   string        $name  HTML name attribute for the control.
	 *
	 * @throws  UnknownFieldTypeException If the field declares a type outside both the taxonomy and the custom-type registry.
	 *
	 * @return  string
	 */
	public function render( SettingsField $field, mixed $value, string $name ): string {
		$type = FieldType::tryFrom( $field->type );
		if ( null === $type ) {
			if ( isset( $this->custom_types[ $field->type ] ) ) {
				return ( $this->custom_types[ $field->type ]->render )( $field, $value, $name ) . $this->render_description( $field );
			}
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- framework-internal exception; never reaches an HTML output context unescaped.
			throw new UnknownFieldTypeException( "Unknown settings field type: '$field->type'" );
		}

		$control = match ( $type ) {
			FieldType::Textarea    => $this->render_textarea( $field, $value, $name ),
			FieldType::Checkbox    => $this->render_checkbox( $field, $value, $name ),
			FieldType::Select      => $this->render_select( $field, $value, $name, false ),
			FieldType::Multiselect => $this->render_select( $field, $value, $name, true ),
			FieldType::Radio       => $this->render_radio( $field, $value, $name ),
			default                => $this->render_input( $field, $value, $name, $type ),
		};

		return $control . $this->render_description( $field );
	}

	// endregion

	// region HELPERS

	/**
	 * Renders a single-line input (text, number, email, url).
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   SettingsField $field Field to render.
	 * @param   mixed         $value Current value.
	 * @param   string        $name  HTML name attribute.
	 * @param   FieldType     $type  Resolved field type supplying the input type attribute.
	 *
	 * @return  string
	 */
	protected function render_input( SettingsField $field, mixed $value, string $name, FieldType $type ): string {
		return \sprintf(
			'<input type="%s" name="%s" value="%s"%s />',
			\esc_attr( $type->value ),
			\esc_attr( $name ),
			\esc_attr( $this->stringify( $value ) ),
			$this->render_attributes( $field->attributes ),
		);
	}

	/**
	 * Renders a textarea.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   SettingsField $field Field to render.
	 * @param   mixed         $value Current value.
	 * @param   string        $name  HTML name attribute.
	 *
	 * @return  string
	 */
	protected function render_textarea( SettingsField $field, mixed $value, string $name ): string {
		return \sprintf(
			'<textarea name="%s"%s>%s</textarea>',
			\esc_attr( $name ),
			$this->render_attributes( $field->attributes ),
			\esc_textarea( $this->stringify( $value ) ),
		);
	}

	/**
	 * Renders a checkbox, checked for a truthy value.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   SettingsField $field Field to render.
	 * @param   mixed         $value Current value.
	 * @param   string        $name  HTML name attribute.
	 *
	 * @return  string
	 */
	protected function render_checkbox( SettingsField $field, mixed $value, string $name ): string {
		return \sprintf(
			'<input type="checkbox" name="%s" value="1"%s%s />',
			\esc_attr( $name ),
			\checked( is_checkbox_checked( $value ), true, false ),
			$this->render_attributes( $field->attributes ),
		);
	}

	/**
	 * Renders a single- or multi-select, marking the current value(s) selected.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   SettingsField $field    Field to render.
	 * @param   mixed         $value    Current value; an array for a multi-select.
	 * @param   string        $name     HTML name attribute.
	 * @param   bool          $multiple Whether to render a multi-select.
	 *
	 * @return  string
	 */
	protected function render_select( SettingsField $field, mixed $value, string $name, bool $multiple ): string {
		$selected = array();
		foreach ( $multiple ? ( \is_array( $value ) ? $value : array() ) : array( $value ) as $selected_value ) {
			$selected[] = $this->stringify( $selected_value );
		}

		$options = '';
		foreach ( $this->resolver->resolve( $field->options ) as $option_value => $label ) {
			$options .= \sprintf(
				'<option value="%s"%s>%s</option>',
				\esc_attr( (string) $option_value ),
				\selected( \in_array( (string) $option_value, $selected, true ), true, false ),
				\esc_html( $this->stringify( $label ) ),
			);
		}

		return \sprintf(
			'<select name="%s"%s%s>%s</select>',
			\esc_attr( $multiple ? $name . '[]' : $name ),
			$multiple ? ' multiple' : '',
			$this->render_attributes( $field->attributes ),
			$options,
		);
	}

	/**
	 * Renders a labelled radio input per option, checking the current value.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   SettingsField $field Field to render.
	 * @param   mixed         $value Current value.
	 * @param   string        $name  HTML name attribute.
	 *
	 * @return  string
	 */
	protected function render_radio( SettingsField $field, mixed $value, string $name ): string {
		$current  = $this->stringify( $value );
		$rendered = '';
		foreach ( $this->resolver->resolve( $field->options ) as $option_value => $label ) {
			$rendered .= \sprintf(
				'<label><input type="radio" name="%s" value="%s"%s%s /> %s</label>',
				\esc_attr( $name ),
				\esc_attr( (string) $option_value ),
				\checked( (string) $option_value, $current, false ),
				$this->render_attributes( $field->attributes ),
				\esc_html( $this->stringify( $label ) ),
			);
		}

		return $rendered;
	}

	/**
	 * Renders a descriptor's extra HTML attributes as escaped name="value" pairs.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<string, scalar> $attributes Attribute map.
	 *
	 * @return  string
	 */
	protected function render_attributes( array $attributes ): string {
		$rendered = '';
		foreach ( filter_field_attributes( $attributes ) as $attribute => $attribute_value ) {
			$rendered .= \sprintf( ' %s="%s"', \esc_attr( $attribute ), \esc_attr( (string) $attribute_value ) );
		}

		return $rendered;
	}

	/**
	 * Coerces a value to a string for output; a non-scalar becomes an empty string.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed $value Value to stringify.
	 *
	 * @return  string
	 */
	protected function stringify( mixed $value ): string {
		return \is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Renders a field's help text as an escaped description paragraph, after the control; empty when none is set.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   SettingsField $field Field whose description to render.
	 *
	 * @return  string
	 */
	protected function render_description( SettingsField $field ): string {
		if ( null === $field->description ) {
			return '';
		}

		return \sprintf( '<p class="description">%s</p>', \esc_html( $field->description ) );
	}

	// endregion
}
