<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings;

\defined( 'ABSPATH' ) || exit;

/**
 * Renders the controls of field arrays, sanitizes their submissions and verifies object-form requests.
 *
 * A field array is a WooCommerce settings array whose title the host prints as the row label. Keys are added to the shape, never repurposed.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @phpstan-type Field array{type: string, id?: string, title?: string, desc?: string, desc_tip?: bool|string, default?: mixed, options?: array<int|string, string>, placeholder?: string, class?: string, css?: string, custom_attributes?: array<string, scalar>, disabled?: bool|list<int|string>, validate?: callable(mixed): mixed, render?: callable(array<string, mixed>, mixed, string, string): string}
 */
final class Forms {
	// region FIELDS AND CONSTANTS

	/**
	 * The class each text-like control gets when its field sets none, as on WordPress core's settings screens.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     array<string, string>
	 */
	protected const array SIZING_CLASSES = array(
		'text'     => 'regular-text',
		'email'    => 'regular-text',
		'url'      => 'regular-text',
		'number'   => 'small-text',
		'textarea' => 'large-text',
	);

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	protected function __construct() {}

	// endregion

	// region METHODS

	/**
	 * Returns the escaped markup of the field's control, without its row label.
	 *
	 * A radio, a checkboxes group and a checkbox with a desc render in a fieldset named by the title, so their host prints the row label as plain text, while every other control carries the id the row label points at. An enabled checkbox or multi-value control posts a hidden input first, so a field absent from a submission was not rendered or is disabled. A field with a render callable renders through it, and the callable escapes, names and describes its control and, unless the field is disabled, prints a hidden empty input before it.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   Field  $field The field.
	 * @param   mixed  $value The stored value.
	 * @param   string $name  The name the control posts under.
	 * @param   string $id    The control's HTML id, which also prefixes the ids of its choices and help.
	 *
	 * @return  string
	 */
	public static function render_control( array $field, mixed $value, string $name, string $id ): string {
		if ( isset( $field['render'] ) ) {
			return $field['render']( $field, $value, $name, $id );
		}

		return match ( $field['type'] ) {
			'text', 'number', 'email', 'url' => self::render_input( $field, $value, $name, $id ),
			'textarea'                       => self::render_textarea( $field, $value, $name, $id ),
			'checkbox'                       => self::render_checkbox( $field, $value, $name, $id ),
			'select', 'multiselect'          => self::render_select( $field, $value, $name, $id ),
			'radio', 'checkboxes'            => self::render_choices( $field, $value, $name, $id ),
			default                          => '',
		};
	}

	/**
	 * Returns whether the field is disabled, which holds when its custom attributes carry disabled, whatever its value, or a checkbox's top-level disabled is truthy.
	 *
	 * That is where WooCommerce renders a control disabled, and browsers submit no disabled control. A radio's top-level disabled lists disabled choices instead, and other types ignore it.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   Field $field The field.
	 *
	 * @return  bool
	 */
	public static function is_disabled( array $field ): bool {
		return \array_key_exists( 'disabled', \array_change_key_case( $field['custom_attributes'] ?? array() ) ) || ( 'checkbox' === $field['type'] && (bool) ( $field['disabled'] ?? false ) );
	}

	// endregion

	// region HELPERS

	/**
	 * Returns the markup of a text, number, email or url input.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   Field  $field The field.
	 * @param   mixed  $value The stored value.
	 * @param   string $name  The name the control posts under.
	 * @param   string $id    The control's HTML id.
	 *
	 * @return  string
	 */
	protected static function render_input( array $field, mixed $value, string $name, string $id ): string {
		$help   = self::get_help( $field, $id );
		$custom = self::get_custom_attributes( $field );
		if ( 'number' === $field['type'] ) {
			// The default step of 1 would make a stored decimal invalid, and the browser would then refuse to submit the form.
			$custom += array( 'step' => 'any' );
		}

		$attributes = array(
			'type'             => $field['type'],
			'id'               => $id,
			'name'             => $name,
			'value'            => self::to_string( $value ),
			'class'            => $field['class'] ?? self::SIZING_CLASSES[ $field['type'] ],
			'style'            => $field['css'] ?? null,
			'placeholder'      => $field['placeholder'] ?? null,
			'disabled'         => self::is_disabled( $field ),
			'aria-describedby' => self::get_described_by( $help ),
		);

		return '<input' . self::render_attributes( $attributes, $custom ) . ' />' . self::render_help( $help );
	}

	/**
	 * Returns the markup of a textarea.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   Field  $field The field.
	 * @param   mixed  $value The stored value.
	 * @param   string $name  The name the control posts under.
	 * @param   string $id    The control's HTML id.
	 *
	 * @return  string
	 */
	protected static function render_textarea( array $field, mixed $value, string $name, string $id ): string {
		$help       = self::get_help( $field, $id );
		$attributes = array(
			'id'               => $id,
			'name'             => $name,
			'class'            => $field['class'] ?? self::SIZING_CLASSES['textarea'],
			'style'            => $field['css'] ?? null,
			'placeholder'      => $field['placeholder'] ?? null,
			'disabled'         => self::is_disabled( $field ),
			'aria-describedby' => self::get_described_by( $help ),
		);

		return '<textarea' . self::render_attributes( $attributes, self::get_custom_attributes( $field ) ) . '>' . \esc_textarea( self::to_string( $value ) ) . '</textarea>' . self::render_help( $help );
	}

	/**
	 * Returns the markup of a checkbox, which posts yes when checked and the preceding hidden no otherwise.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   Field  $field The field.
	 * @param   mixed  $value The stored value.
	 * @param   string $name  The name the control posts under.
	 * @param   string $id    The control's HTML id.
	 *
	 * @return  string
	 */
	protected static function render_checkbox( array $field, mixed $value, string $name, string $id ): string {
		$help       = self::get_help( $field, $id );
		$disabled   = self::is_disabled( $field );
		$attributes = array(
			'type'             => 'checkbox',
			'id'               => $id,
			'name'             => $name,
			'value'            => 'yes',
			'class'            => $field['class'] ?? null,
			'style'            => $field['css'] ?? null,
			'checked'          => 'yes' === $value,
			'disabled'         => $disabled,
			'aria-describedby' => self::get_described_by( $help ),
		);
		$control    = ( $disabled ? '' : self::render_hidden( $name, 'no' ) ) . '<input' . self::render_attributes( $attributes, self::get_custom_attributes( $field ) ) . ' />';

		if ( '' === ( $field['desc'] ?? '' ) ) {
			return $control . self::render_help( $help );
		}

		return '<fieldset>' . self::render_legend( $field ) . '<label for="' . \esc_attr( $id ) . '">' . $control . ' ' . \wp_kses_post( $field['desc'] ) . '</label>' . self::render_help( $help ) . '</fieldset>';
	}

	/**
	 * Returns the markup of a select or multiselect.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   Field  $field The field.
	 * @param   mixed  $value The stored value.
	 * @param   string $name  The name the control posts under.
	 * @param   string $id    The control's HTML id.
	 *
	 * @return  string
	 */
	protected static function render_select( array $field, mixed $value, string $name, string $id ): string {
		$help     = self::get_help( $field, $id );
		$multiple = 'multiselect' === $field['type'];
		$disabled = self::is_disabled( $field );
		$chosen   = self::get_chosen( $value, $multiple );

		$options = '';
		foreach ( $field['options'] ?? array() as $key => $label ) {
			$options .= '<option' . self::render_attributes(
				array(
					'value'    => (string) $key,
					'selected' => \in_array( (string) $key, $chosen, true ),
				)
			) . '>' . \esc_html( $label ) . '</option>';
		}

		$attributes = array(
			'id'               => $id,
			'name'             => $multiple ? $name . '[]' : $name,
			'multiple'         => $multiple,
			'class'            => $field['class'] ?? null,
			'style'            => $field['css'] ?? null,
			'disabled'         => $disabled,
			'aria-describedby' => self::get_described_by( $help ),
		);

		return ( $multiple && ! $disabled ? self::render_hidden( $name, '' ) : '' ) . '<select' . self::render_attributes( $attributes, self::get_custom_attributes( $field ) ) . '>' . $options . '</select>' . self::render_help( $help );
	}

	/**
	 * Returns the markup of a radio or checkboxes group, with positional choice ids so no choice key can collide with another id.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   Field  $field The field.
	 * @param   mixed  $value The stored value.
	 * @param   string $name  The name the group posts under.
	 * @param   string $id    The HTML id that prefixes the choice and help ids.
	 *
	 * @return  string
	 */
	protected static function render_choices( array $field, mixed $value, string $name, string $id ): string {
		$help     = self::get_help( $field, $id );
		$multiple = 'checkboxes' === $field['type'];
		$disabled = self::is_disabled( $field );
		$chosen   = self::get_chosen( $value, $multiple );
		$custom   = self::get_custom_attributes( $field );
		$excluded = ! $multiple && \is_array( $field['disabled'] ?? null ) ? $field['disabled'] : array();

		$choices = $multiple && ! $disabled ? self::render_hidden( $name, '' ) : '';
		$index   = 0;
		foreach ( $field['options'] ?? array() as $key => $label ) {
			$attributes = array(
				'type'     => $multiple ? 'checkbox' : 'radio',
				'id'       => "$id.$index",
				'name'     => $multiple ? $name . '[]' : $name,
				'value'    => (string) $key,
				'class'    => $field['class'] ?? null,
				'style'    => $field['css'] ?? null,
				'checked'  => \in_array( (string) $key, $chosen, true ),
				'disabled' => $disabled || \in_array( $key, $excluded, true ),
			);
			$choices   .= '<label for="' . \esc_attr( "$id.$index" ) . '"><input' . self::render_attributes( $attributes, $custom ) . ' /> ' . \esc_html( $label ) . '</label><br />';
			++$index;
		}

		return '<fieldset' . self::render_attributes( array( 'aria-describedby' => self::get_described_by( $help ) ) ) . '>' . self::render_legend( $field ) . $choices . self::render_help( $help ) . '</fieldset>';
	}

	/**
	 * Returns the markup of a hidden input.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $name  The name the input posts under.
	 * @param   string $value The value the input posts.
	 *
	 * @return  string
	 */
	protected static function render_hidden( string $name, string $value ): string {
		return '<input' . self::render_attributes(
			array(
				'type'  => 'hidden',
				'name'  => $name,
				'value' => $value,
			)
		) . ' />';
	}

	/**
	 * Returns the screen-reader legend that names a fieldset by the field's title, or nothing when the field has no title.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   Field $field The field.
	 *
	 * @return  string
	 */
	protected static function render_legend( array $field ): string {
		return '' === ( $field['title'] ?? '' ) ? '' : '<legend class="screen-reader-text"><span>' . \esc_html( $field['title'] ) . '</span></legend>';
	}

	/**
	 * Returns the markup of the help paragraphs.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<string, string> $help The help texts, keyed by their ids.
	 *
	 * @return  string
	 */
	protected static function render_help( array $help ): string {
		$html = '';
		foreach ( $help as $help_id => $text ) {
			$html .= '<p class="description" id="' . \esc_attr( $help_id ) . '">' . \wp_kses_post( $text ) . '</p>';
		}

		return $html;
	}

	/**
	 * Returns the attributes as HTML, the framework's own first, so a custom attribute never overrides one.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<string, string|bool|null> $own    The framework's attributes, where true prints a bare attribute and false or null prints none.
	 * @param   array<string, string>           $custom The field's custom attributes.
	 *
	 * @return  string
	 */
	protected static function render_attributes( array $own, array $custom = array() ): string {
		$html = '';
		foreach ( \array_filter( $own, static fn ( string|bool|null $value ): bool => ! \is_null( $value ) && false !== $value ) + $custom as $name => $value ) {
			$html .= true === $value ? " $name" : " $name=\"" . \esc_attr( $value ) . '"';
		}

		return $html;
	}

	/**
	 * Returns the field's help texts keyed by their ids: a desc that is not a checkbox's label, then a string desc_tip.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   Field  $field The field.
	 * @param   string $id    The control's HTML id.
	 *
	 * @return  array<string, string>
	 */
	protected static function get_help( array $field, string $id ): array {
		$help = array();
		if ( 'checkbox' !== $field['type'] && '' !== ( $field['desc'] ?? '' ) ) {
			$help[ "$id.description" ] = $field['desc'];
		}
		if ( \is_string( $field['desc_tip'] ?? null ) && '' !== $field['desc_tip'] ) {
			$help[ "$id.help" ] = $field['desc_tip'];
		}

		return $help;
	}

	/**
	 * Returns the aria-describedby value that links the help texts, or null when there are none.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<string, string> $help The help texts, keyed by their ids.
	 *
	 * @return  string|null
	 */
	protected static function get_described_by( array $help ): ?string {
		return array() === $help ? null : \implode( ' ', \array_keys( $help ) );
	}

	/**
	 * Returns the field's custom attributes whose names are valid and not event handlers, lowercased and with string values.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   Field $field The field.
	 *
	 * @return  array<string, string>
	 */
	protected static function get_custom_attributes( array $field ): array {
		$attributes = array();
		foreach ( $field['custom_attributes'] ?? array() as $name => $value ) {
			$name = \strtolower( (string) $name );
			if ( 1 === \preg_match( '/\A[a-z][a-z0-9-]*\z/', $name ) && ! \str_starts_with( $name, 'on' ) ) {
				$attributes[ $name ] = (string) $value;
			}
		}

		return $attributes;
	}

	/**
	 * Returns the stored choices as strings, so integer-keyed options match the strings a form posts.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed $value    The stored value.
	 * @param   bool  $multiple Whether the field holds a list of choices.
	 *
	 * @return  list<string>
	 */
	protected static function get_chosen( mixed $value, bool $multiple ): array {
		if ( ! $multiple ) {
			return array( self::to_string( $value ) );
		}

		return \is_array( $value ) ? \array_values( \array_map( self::to_string( ... ), $value ) ) : array();
	}

	/**
	 * Returns a scalar as a string, and anything else as an empty string.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed $value The value.
	 *
	 * @return  string
	 */
	protected static function to_string( mixed $value ): string {
		return \is_scalar( $value ) ? (string) $value : '';
	}

	// endregion
}
