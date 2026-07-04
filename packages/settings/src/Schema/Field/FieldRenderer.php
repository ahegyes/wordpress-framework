<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Schema\Field;

use DeepWebSolutions\Framework\Settings\Schema\Exceptions\UnknownFieldTypeException;
use DeepWebSolutions\Framework\Settings\Schema\Options\OptionsResolver;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\CustomFieldType;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsField;

use function DeepWebSolutions\Framework\Settings\Schema\field_control_id;
use function DeepWebSolutions\Framework\Settings\Schema\filter_field_attributes;
use function DeepWebSolutions\Framework\Settings\Schema\is_checkbox_checked;
use function DeepWebSolutions\Framework\Settings\Schema\resolve_field_control_id;
use function DeepWebSolutions\Framework\Settings\Schema\stringify_for_output;

/**
 * Renders a field descriptor to an escaped HTML control with its value bound.
 *
 * The shared field-type render layer for backends that render the framework's own
 * field controls; a backend that provides its own rendering does not use it. Resolves
 * a field's options through the same {@see OptionsResolver} the processor validates
 * against, so the rendered choices and the accepted values always agree. The caller
 * supplies the control's HTML name; a field type outside the taxonomy and the injected
 * custom-type registry throws.
 *
 * Each built-in control carries a DOM id — a descriptor-supplied 'id' attribute, or
 * the id {@see field_control_id()} derives from the control's name — so a surface
 * label-for resolved the same way ({@see resolve_field_control_id()}) targets the
 * control; a radio group carries it on its fieldset and is named by the fieldset's
 * screen-reader legend instead. A described field's help paragraph carries that id
 * suffixed '..description' (a doubled dot no derived control id can contain), referenced via aria-describedby (merged with a
 * descriptor-supplied reference list) from the control or the radio fieldset.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final readonly class FieldRenderer {
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
				// A custom renderer owns its control markup, so no control id is guaranteed and the
				// description stays unassociated.
				return ( $this->custom_types[ $field->type ]->render )( $field, $value, $name ) . $this->render_description( $field, '' );
			}
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- framework-internal exception; never reaches an HTML output context unescaped.
			throw new UnknownFieldTypeException( "Unknown settings field type: '$field->type'" );
		}

		$control_id = resolve_field_control_id( $field, $name );

		// The '..description' suffix cannot collide with any control id: derived ids join non-empty
		// segments on single dots, so a doubled dot is unreachable. A whitespace-bearing descriptor id
		// cannot seed a description id — whitespace splits an IDREF list into dangling tokens.
		$description_id = '' !== $control_id && null !== $field->description && 1 !== \preg_match( '/\s/', $control_id )
			? $control_id . '..description'
			: '';
		$identity       = $this->render_dom_id( $control_id ) . $this->render_describedby( $field, $description_id );

		$control = match ( $type ) {
			FieldType::Textarea    => $this->render_textarea( $field, $value, $name, $identity ),
			FieldType::Checkbox    => $this->render_checkbox( $field, $value, $name, $identity ),
			FieldType::Select      => $this->render_select( $field, $value, $name, false, $identity ),
			FieldType::Multiselect => $this->render_select( $field, $value, $name, true, $identity ),
			FieldType::Radio       => $this->render_radio( $field, $value, $name, $identity ),
			default                => $this->render_input( $field, $value, $name, $type, $identity ),
		};

		return $control . $this->render_description( $field, $description_id );
	}

	// endregion

	// region HELPERS

	/**
	 * Renders a single-line input (text, number, email, url).
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   SettingsField $field    Field to render.
	 * @param   mixed         $value    Current value.
	 * @param   string        $name     HTML name attribute.
	 * @param   FieldType     $type     Resolved field type supplying the input type attribute.
	 * @param   string        $identity Escaped id and aria-describedby attributes for the control; '' emits none.
	 *
	 * @return  string
	 */
	protected function render_input( SettingsField $field, mixed $value, string $name, FieldType $type, string $identity ): string {
		return \sprintf(
			'<input type="%s"%s name="%s" value="%s"%s />',
			\esc_attr( $type->value ),
			$identity,
			\esc_attr( $name ),
			\esc_attr( stringify_for_output( $value ) ),
			$this->render_attributes( $field->attributes ),
		);
	}

	/**
	 * Renders a textarea.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   SettingsField $field    Field to render.
	 * @param   mixed         $value    Current value.
	 * @param   string        $name     HTML name attribute.
	 * @param   string        $identity Escaped id and aria-describedby attributes for the control; '' emits none.
	 *
	 * @return  string
	 */
	protected function render_textarea( SettingsField $field, mixed $value, string $name, string $identity ): string {
		return \sprintf(
			'<textarea%s name="%s"%s>%s</textarea>',
			$identity,
			\esc_attr( $name ),
			$this->render_attributes( $field->attributes ),
			\esc_textarea( stringify_for_output( $value ) ),
		);
	}

	/**
	 * Renders a checkbox, checked for a truthy value.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   SettingsField $field    Field to render.
	 * @param   mixed         $value    Current value.
	 * @param   string        $name     HTML name attribute.
	 * @param   string        $identity Escaped id and aria-describedby attributes for the control; '' emits none.
	 *
	 * @return  string
	 */
	protected function render_checkbox( SettingsField $field, mixed $value, string $name, string $identity ): string {
		return \sprintf(
			'<input type="checkbox"%s name="%s" value="1"%s%s />',
			$identity,
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
	 * @param   string        $identity Escaped id and aria-describedby attributes for the control; '' emits none.
	 *
	 * @return  string
	 */
	protected function render_select( SettingsField $field, mixed $value, string $name, bool $multiple, string $identity ): string {
		$selected = array();
		foreach ( $multiple ? ( \is_array( $value ) ? $value : array() ) : array( $value ) as $selected_value ) {
			$selected[] = stringify_for_output( $selected_value );
		}

		$options = '';
		foreach ( $this->resolver->resolve( $field->options ) as $option_value => $label ) {
			$options .= \sprintf(
				'<option value="%s"%s>%s</option>',
				\esc_attr( (string) $option_value ),
				\selected( \in_array( (string) $option_value, $selected, true ), true, false ),
				\esc_html( stringify_for_output( $label ) ),
			);
		}

		return \sprintf(
			'<select%s name="%s"%s%s>%s</select>',
			$identity,
			\esc_attr( $multiple ? $name . '[]' : $name ),
			$multiple ? ' multiple' : '',
			$this->render_attributes( $field->attributes ),
			$options,
		);
	}

	/**
	 * Renders a radio group: a fieldset carrying the field's identity, whose screen-reader legend carries
	 * the field label, with a labelled radio input per option, checking the current value.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   SettingsField $field    Field to render.
	 * @param   mixed         $value    Current value.
	 * @param   string        $name     HTML name attribute.
	 * @param   string        $identity Escaped id and aria-describedby attributes for the fieldset; '' emits none.
	 *
	 * @return  string
	 */
	protected function render_radio( SettingsField $field, mixed $value, string $name, string $identity ): string {
		$current  = stringify_for_output( $value );
		$rendered = '';
		foreach ( $this->resolver->resolve( $field->options ) as $option_value => $label ) {
			$rendered .= \sprintf(
				'<label><input type="radio" name="%s" value="%s"%s%s /> %s</label>',
				\esc_attr( $name ),
				\esc_attr( (string) $option_value ),
				\checked( (string) $option_value, $current, false ),
				$this->render_attributes( $field->attributes ),
				\esc_html( stringify_for_output( $label ) ),
			);
		}

		return \sprintf(
			'<fieldset%s><legend class="screen-reader-text">%s</legend>%s</fieldset>',
			$identity,
			\esc_html( $field->label ),
			$rendered,
		);
	}

	/**
	 * Renders a DOM id as an escaped id attribute; empty when no valid id was derived.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $dom_id DOM id to render; '' renders nothing.
	 *
	 * @return  string
	 */
	protected function render_dom_id( string $dom_id ): string {
		return '' === $dom_id ? '' : \sprintf( ' id="%s"', \esc_attr( $dom_id ) );
	}

	/**
	 * Renders the control's aria-describedby as one escaped attribute: a descriptor-supplied reference
	 * list merged with the generated description id, consumer tokens first, without duplicates; empty
	 * when neither exists.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   SettingsField $field          Field whose descriptor may carry its own reference list.
	 * @param   string        $description_id Generated description-paragraph id; '' appends none.
	 *
	 * @return  string
	 */
	protected function render_describedby( SettingsField $field, string $description_id ): string {
		$tokens = array();
		foreach ( $field->attributes as $attribute => $value ) {
			if ( 0 === \strcasecmp( (string) $attribute, 'aria-describedby' ) ) {
				$split  = \preg_split( '/\s+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY );
				$tokens = false === $split ? array() : $split;
				break;
			}
		}
		if ( '' !== $description_id ) {
			$tokens[] = $description_id;
		}
		$tokens = \array_values( \array_unique( $tokens ) );

		return array() === $tokens ? '' : \sprintf( ' aria-describedby="%s"', \esc_attr( \implode( ' ', $tokens ) ) );
	}

	/**
	 * Renders a descriptor's extra HTML attributes as escaped name="value" pairs. The 'id' and
	 * 'aria-describedby' keys are skipped in any casing — both are consumed into the control's
	 * identity fragment, so passing them through would duplicate the attribute.
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
			if ( 0 === \strcasecmp( $attribute, 'id' ) || 0 === \strcasecmp( $attribute, 'aria-describedby' ) ) {
				continue;
			}
			$rendered .= \sprintf( ' %s="%s"', \esc_attr( $attribute ), \esc_attr( (string) $attribute_value ) );
		}

		return $rendered;
	}

	/**
	 * Renders a field's help text as an escaped description paragraph, after the control, carrying the
	 * given DOM id so the control's aria-describedby resolves to it; empty when none is set.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   SettingsField $field          Field whose description to render.
	 * @param   string        $description_id DOM id for the paragraph; '' emits none.
	 *
	 * @return  string
	 */
	protected function render_description( SettingsField $field, string $description_id ): string {
		if ( null === $field->description ) {
			return '';
		}

		return \sprintf( '<p class="description"%s>%s</p>', $this->render_dom_id( $description_id ), \esc_html( $field->description ) );
	}

	// endregion
}
