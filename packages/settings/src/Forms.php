<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings;

use DeepWebSolutions\Framework\Shared\Exception\InvalidArgumentException;

\defined( 'ABSPATH' ) || exit;

/**
 * Renders the controls of field arrays, sanitizes their submissions and names their rejections, for every settings helper and object form.
 *
 * A field array is a WooCommerce settings array whose title the host prints as the row label. Keys are added to the shape, never repurposed.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @phpstan-type Field array{type: string, id?: string, title?: string, desc?: string, desc_tip?: bool|string, default?: mixed, options?: array<int|string, string>, placeholder?: string, class?: string, css?: string, custom_attributes?: array<string, scalar>, disabled?: bool|list<int|string>, validate?: callable(mixed): mixed, render?: callable(array<string, mixed>, mixed, string, string): string}
 * @phpstan-type FieldRow array{field: Field, value: mixed, name: string, id: string, label_for?: string}
 */
final class Forms {
	// region FIELDS AND CONSTANTS

	/**
	 * The custom attributes each type's control gets unless its field sets them, merged under the field's own.
	 *
	 * A number input's default step of 1 would make a stored decimal invalid, and the browser would then refuse to submit the form.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     array<string, array<string, string>>
	 */
	public const array DEFAULT_CUSTOM_ATTRIBUTES = array(
		'number' => array( 'step' => 'any' ),
	);

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
	 * A radio, a checkboxes group and a checkbox with a desc render in a fieldset named by the title, so their host prints the row label as plain text, while every other control carries the id the row label points at. An enabled checkbox or multi-value control posts a hidden input first, so a field absent from a submission was not rendered or is disabled. A field with a render callable renders through it, and the callable escapes, names and describes its control, a fieldset named by the title for a group, and, unless the field is disabled, prints a hidden empty input before it.
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
	 * Returns the value to store for the field's submission, or a WP_Error under which the host keeps the stored value.
	 *
	 * A field with validate hands the submission, alone, to the callable, which returns the value to store or a WP_Error and owns the field's allowed values, so no type sanitizer runs before or after it. Without validate, the field's type sanitizes the submission and checks its choices, and a rejection carries a code and a message that tells the user what to enter, which a host may replace by the code. Every WP_Error message is plain text, which the host escapes.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   Field $field The field.
	 * @param   mixed $raw   The unslashed submission, in which a cleared multi-value field is an empty string.
	 *
	 * @return  mixed|\WP_Error
	 */
	#[\NoDiscard]
	public static function sanitize_value( array $field, mixed $raw ): mixed {
		if ( '' === $raw && \in_array( $field['type'], array( 'multiselect', 'checkboxes' ), true ) ) {
			$raw = array();
		}

		if ( isset( $field['validate'] ) ) {
			return $field['validate']( $raw );
		}
		if ( isset( $field['render'] ) ) {
			return self::reject( 'missing_validate' );
		}

		return match ( $field['type'] ) {
			'text'                      => self::is_text( $raw ) ? sanitize_text_field( (string) $raw ) : self::reject( 'invalid_value' ),
			'textarea'                  => self::is_text( $raw ) ? sanitize_textarea_field( (string) $raw ) : self::reject( 'invalid_value' ),
			'email'                     => self::sanitize_email( $raw ),
			'url'                       => self::sanitize_url( $raw ),
			'number'                    => self::sanitize_number( $raw ),
			'checkbox'                  => self::sanitize_checkbox( $raw ),
			'select', 'radio'           => self::sanitize_choice( $field, $raw ),
			'multiselect', 'checkboxes' => self::sanitize_choices( $field, $raw ),
			default                     => self::reject( 'unknown_type' ),
		};
	}

	/**
	 * Returns the accepted values and the rejections of a submission, keyed by field id, for the defined, present and enabled fields only.
	 *
	 * Callers merge the values onto the stored row with array_replace( $stored, $result['values'] ) and report the errors, so a submission writes no id outside the definition, and every stored id it leaves out, including those of rejected fields and of fields absent from the definition, keeps its value.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   list<Field>             $fields    The fields.
	 * @param   array<array-key, mixed> $submitted The unslashed submission, keyed by field id.
	 *
	 * @return  array{values: array<string, mixed>, errors: array<string, \WP_Error>}
	 */
	#[\NoDiscard]
	public static function sanitize_fields( array $fields, array $submitted ): array {
		$result = array(
			'values' => array(),
			'errors' => array(),
		);
		foreach ( $fields as $field ) {
			$id = $field['id'] ?? null;
			if ( \is_null( $id ) || ! \array_key_exists( $id, $submitted ) || self::is_disabled( $field ) ) {
				continue;
			}

			$value = self::sanitize_value( $field, $submitted[ $id ] );
			if ( is_wp_error( $value ) ) {
				$result['errors'][ $id ] = $value;
			} else {
				$result['values'][ $id ] = $value;
			}
		}

		return $result;
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

	/**
	 * Returns whether the field renders as a group in a fieldset its title names, which holds for a radio, a checkboxes group and a checkbox with a desc.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   Field $field The field.
	 *
	 * @return  bool
	 */
	public static function is_group( array $field ): bool {
		return \in_array( $field['type'], array( 'radio', 'checkboxes' ), true ) || ( 'checkbox' === $field['type'] && '' !== ( $field['desc'] ?? '' ) );
	}

	/**
	 * Returns the field's title, or its id when the title is empty, so that every row label, legend and rejection names its field.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   Field $field The field.
	 *
	 * @return  string
	 */
	public static function get_title( array $field ): string {
		return '' !== ( $field['title'] ?? '' ) ? $field['title'] : ( $field['id'] ?? '' );
	}

	/**
	 * Returns the plain-text message for a rejected field, which names the field and adds the rejection's message when it has one.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string    $title The field's title.
	 * @param   \WP_Error $error The rejection.
	 *
	 * @return  string
	 */
	public static function get_rejection_message( string $title, \WP_Error $error ): string {
		$message = $error->get_error_message();
		if ( '' === $message ) {
			/* translators: %s: The field's title. */
			return \sprintf( __( '%s was not saved because its value is not valid, so it keeps its previous value.', 'wp-framework' ), $title );
		}

		/* translators: 1: The field's title. 2: What to enter instead. */
		return \sprintf( __( '%1$s was not saved, so it keeps its previous value. %2$s', 'wp-framework' ), $title, $message );
	}

	/**
	 * Throws when the id is not valid as the id of a settings helper or an object form.
	 *
	 * The pattern accepts every v1 option key, meta key, prefix and slug, so that ports keep their keys.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $id The id.
	 *
	 * @throws  InvalidArgumentException Thrown when the id is not a lowercase letter, after an optional underscore, followed by lowercase letters, digits, underscores and hyphens.
	 *
	 * @return  void
	 */
	public static function check_helper_id( string $id ): void {
		if ( 1 !== \preg_match( '/\A_?[a-z][a-z0-9_-]*\z/', $id ) ) {
			throw new InvalidArgumentException( "Helper id '$id' is not a lowercase letter, after an optional underscore, followed by lowercase letters, digits, underscores and hyphens." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing, never rendered.
		}
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
	public static function render_hidden_input( string $name, string $value ): string {
		return '<input' . self::render_attributes(
			array(
				'type'  => 'hidden',
				'name'  => $name,
				'value' => $value,
			)
		) . ' />';
	}

	/**
	 * Returns the value escaped for an HTML attribute, which the browser decodes back to the value exactly and which esc_attr() leaves unchanged.
	 *
	 * Its ampersands are encoded first, because esc_attr() leaves existing entities as they are, so a stored &amp; would otherwise post back as &.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $value The value.
	 *
	 * @return  string
	 */
	public static function escape_attribute( string $value ): string {
		return esc_attr( \str_replace( '&', '&amp;', $value ) );
	}

	/**
	 * Returns a row for each field with an id: the field named by its title, its stored value or else its default, the name it posts under, its HTML id, and the id its row label points at unless it is a group.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @internal
	 *
	 * @param   list<Field>             $fields The fields.
	 * @param   array<array-key, mixed> $values The stored values, keyed by field id.
	 * @param   string                  $prefix The prefix of the names and HTML ids, such as an option or a form id.
	 *
	 * @return  list<FieldRow>
	 */
	public static function get_rows( array $fields, array $values, string $prefix ): array {
		$rows = array();
		foreach ( $fields as $field ) {
			if ( ! isset( $field['id'] ) ) {
				continue;
			}

			$field['title'] = self::get_title( $field );

			$row = array(
				'field' => $field,
				'value' => \array_key_exists( $field['id'], $values ) ? $values[ $field['id'] ] : ( $field['default'] ?? '' ),
				'name'  => "{$prefix}[{$field['id']}]",
				'id'    => "$prefix.{$field['id']}",
			);
			if ( ! self::is_group( $field ) ) {
				$row['label_for'] = $row['id'];
			}
			$rows[] = $row;
		}

		return $rows;
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
		$custom = self::get_custom_attributes( $field ) + ( self::DEFAULT_CUSTOM_ATTRIBUTES[ $field['type'] ] ?? array() );

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

		// HTML parsing drops a newline right after the opening tag, so one is printed for it to drop instead of the value's own.
		return '<textarea' . self::render_attributes( $attributes, self::get_custom_attributes( $field ) ) . ">\n" . esc_textarea( self::to_string( $value ) ) . '</textarea>' . self::render_help( $help );
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
		$control    = ( $disabled ? '' : self::render_hidden_input( $name, 'no' ) ) . '<input' . self::render_attributes( $attributes, self::get_custom_attributes( $field ) ) . ' />';

		if ( '' === ( $field['desc'] ?? '' ) ) {
			return $control . self::render_help( $help );
		}

		return '<fieldset>' . self::render_legend( $field ) . '<label' . self::render_attributes( array( 'for' => $id ) ) . '>' . $control . ' ' . wp_kses_post( $field['desc'] ) . '</label>' . self::render_help( $help ) . '</fieldset>';
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
			) . '>' . esc_html( $label ) . '</option>';
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

		return ( $multiple && ! $disabled ? self::render_hidden_input( $name, '' ) : '' ) . '<select' . self::render_attributes( $attributes, self::get_custom_attributes( $field ) ) . '>' . $options . '</select>' . self::render_help( $help );
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

		$choices = $multiple && ! $disabled ? self::render_hidden_input( $name, '' ) : '';
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
				'disabled' => $disabled || self::is_disabled_choice( $field, $key ),
			);
			$choices   .= '<label' . self::render_attributes( array( 'for' => "$id.$index" ) ) . '><input' . self::render_attributes( $attributes, $custom ) . ' /> ' . esc_html( $label ) . '</label><br />';
			++$index;
		}

		return '<fieldset' . self::render_attributes( array( 'aria-describedby' => self::get_described_by( $help ) ) ) . '>' . self::render_legend( $field ) . $choices . self::render_help( $help ) . '</fieldset>';
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
		return '' === ( $field['title'] ?? '' ) ? '' : '<legend class="screen-reader-text"><span>' . esc_html( $field['title'] ) . '</span></legend>';
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
			$html .= '<p' . self::render_attributes(
				array(
					'class' => 'description',
					'id'    => $help_id,
				)
			) . '>' . wp_kses_post( $text ) . '</p>';
		}

		return $html;
	}

	/**
	 * Returns the attributes as HTML, where a custom attribute never sets one the framework sets or leaves off.
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
		foreach ( \array_filter( $own + $custom, static fn ( string|bool|null $value ): bool => ! \is_null( $value ) && false !== $value ) as $name => $value ) {
			$html .= true === $value ? " $name" : " $name=\"" . self::escape_attribute( $value ) . '"';
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
				$attributes[ $name ] = self::to_string( $value );
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
	 * Returns the submission as a trimmed email address, or a WP_Error when sanitize_email() would empty or rewrite it, which would erase the stored address or store a different one.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed $raw The unslashed submission.
	 *
	 * @return  string|\WP_Error
	 */
	protected static function sanitize_email( mixed $raw ): string|\WP_Error {
		if ( ! self::is_text( $raw ) ) {
			return self::reject( 'invalid_value' );
		}

		$email = \trim( (string) $raw );
		return '' === $email || ( sanitize_email( $email ) === $email && false !== is_email( $email ) ) ? $email : self::reject( 'invalid_email' );
	}

	/**
	 * Returns the submission as a URL safe to store, or a WP_Error when esc_url_raw() would empty it, as it does for a disallowed or mistyped scheme.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed $raw The unslashed submission.
	 *
	 * @return  string|\WP_Error
	 */
	protected static function sanitize_url( mixed $raw ): string|\WP_Error {
		if ( ! self::is_text( $raw ) ) {
			return self::reject( 'invalid_value' );
		}

		$url = \trim( (string) $raw );
		if ( '' === $url ) {
			return '';
		}

		$safe = esc_url_raw( $url );
		return '' === $safe ? self::reject( 'invalid_url' ) : $safe;
	}

	/**
	 * Returns the submission as a trimmed number string in HTML's floating-point grammar, or a WP_Error for anything else that is not empty.
	 *
	 * A number input renders any other numeric string, such as +1 or 1., as empty, so the next save would clear it.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed $raw The unslashed submission.
	 *
	 * @return  string|\WP_Error
	 */
	protected static function sanitize_number( mixed $raw ): string|\WP_Error {
		if ( \is_float( $raw ) && ! \is_finite( $raw ) ) {
			return self::reject( 'invalid_number' );
		}
		if ( ! self::is_text( $raw ) ) {
			return self::reject( 'invalid_value' );
		}

		$number = \trim( (string) $raw );
		if ( '' === $number ) {
			return '';
		}

		return 1 === \preg_match( '/\A-?(?:\d+(?:\.\d+)?|\.\d+)(?:[eE][-+]?\d+)?\z/', $number ) && \is_finite( (float) $number ) ? $number : self::reject( 'invalid_number' );
	}

	/**
	 * Returns yes or no for a checkbox submission, or a WP_Error for any other value.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed $raw The unslashed submission.
	 *
	 * @return  string|\WP_Error
	 */
	protected static function sanitize_checkbox( mixed $raw ): string|\WP_Error {
		return match ( true ) {
			\in_array( $raw, array( 'yes', '1', true ), true ) => 'yes',
			\in_array( $raw, array( 'no', '', false ), true )  => 'no',
			default                                            => self::reject( 'invalid_choice' ),
		};
	}

	/**
	 * Returns the submitted choice of a select or radio as a string, or a WP_Error when it is not one of the field's enabled choices.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   Field $field The field.
	 * @param   mixed $raw   The unslashed submission.
	 *
	 * @return  string|\WP_Error
	 */
	protected static function sanitize_choice( array $field, mixed $raw ): string|\WP_Error {
		if ( ! self::is_text( $raw ) ) {
			return self::reject( 'invalid_value' );
		}

		return \in_array( (string) $raw, self::get_allowed_choices( $field ), true ) ? (string) $raw : self::reject( 'invalid_choice' );
	}

	/**
	 * Returns the submitted choices of a multiselect or checkboxes group as a list of distinct strings, or a WP_Error when one is not among the field's choices.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   Field $field The field.
	 * @param   mixed $raw   The unslashed submission.
	 *
	 * @return  list<string>|\WP_Error
	 */
	protected static function sanitize_choices( array $field, mixed $raw ): array|\WP_Error {
		if ( ! \is_array( $raw ) ) {
			return self::reject( 'invalid_value' );
		}

		$choices = array();
		foreach ( $raw as $choice ) {
			if ( ! self::is_text( $choice ) ) {
				return self::reject( 'invalid_value' );
			}
			$choices[] = (string) $choice;
		}

		return array() === \array_diff( $choices, self::get_allowed_choices( $field ) ) ? \array_values( \array_unique( $choices ) ) : self::reject( 'invalid_choice' );
	}

	/**
	 * Returns the keys of the field's enabled choices as strings, which is how a form posts them.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   Field $field The field.
	 *
	 * @return  list<string>
	 */
	protected static function get_allowed_choices( array $field ): array {
		$allowed = array();
		foreach ( \array_keys( $field['options'] ?? array() ) as $key ) {
			if ( ! self::is_disabled_choice( $field, $key ) ) {
				$allowed[] = (string) $key;
			}
		}

		return $allowed;
	}

	/**
	 * Returns whether a radio's top-level disabled list names the choice, compared strictly with the choice's key as WooCommerce compares it.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   Field      $field The field.
	 * @param   int|string $key   The choice's key in the field's options.
	 *
	 * @return  bool
	 */
	protected static function is_disabled_choice( array $field, int|string $key ): bool {
		return 'radio' === $field['type'] && \is_array( $field['disabled'] ?? null ) && \in_array( $key, $field['disabled'], true );
	}

	/**
	 * Returns the rejection under the code, with the message that tells the user what to enter instead.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @phpstan-param 'invalid_value'|'invalid_choice'|'invalid_email'|'invalid_url'|'invalid_number'|'unknown_type'|'missing_validate' $code
	 *
	 * @param   string $code The rejection's code.
	 *
	 * @return  \WP_Error
	 */
	protected static function reject( string $code ): \WP_Error {
		return new \WP_Error(
			$code,
			match ( $code ) {
				'invalid_value'    => __( 'Enter a valid value.', 'wp-framework' ),
				'invalid_choice'   => __( 'Choose one of the available options.', 'wp-framework' ),
				'invalid_email'    => __( 'Enter a valid email address, such as name@example.com.', 'wp-framework' ),
				'invalid_url'      => __( 'Enter a valid URL, such as https://example.com.', 'wp-framework' ),
				'invalid_number'   => __( 'Enter a number, such as 12 or 3.5.', 'wp-framework' ),
				'unknown_type'     => __( 'This field cannot be saved because its type is not supported.', 'wp-framework' ),
				'missing_validate' => __( 'This field cannot be saved because nothing validates its value.', 'wp-framework' ),
			}
		);
	}

	/**
	 * Returns whether the value is a string, an int or a finite float, the values a single-value field accepts.
	 *
	 * PHP 8.5 warns when it casts NAN to a string.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @phpstan-assert-if-true string|int|float $value
	 *
	 * @param   mixed $value The value.
	 *
	 * @return  bool
	 */
	protected static function is_text( mixed $value ): bool {
		return \is_string( $value ) || \is_int( $value ) || ( \is_float( $value ) && \is_finite( $value ) );
	}

	/**
	 * Returns a string, an int, a finite float or a bool as a string, and anything else as an empty string.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed $value The value.
	 *
	 * @return  string
	 */
	protected static function to_string( mixed $value ): string {
		return \is_bool( $value ) || self::is_text( $value ) ? (string) $value : '';
	}

	// endregion
}
