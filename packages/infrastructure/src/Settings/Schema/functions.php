<?php declare( strict_types=1 );

/**
 * Schema helper functions. All are WP-free except field_label_html(), meta_box_row_html(),
 * is_field_editable_by_current_user(), and wordpress_field_type_sanitizers(), which call WordPress
 * functions and need the WP runtime.
 *
 * @package DeepWebSolutions\Framework\Settings\Schema
 */

namespace DeepWebSolutions\Framework\Settings\Schema;

use DeepWebSolutions\Framework\Settings\Schema\Exceptions\DuplicateSettingsFieldException;
use DeepWebSolutions\Framework\Settings\Schema\Exceptions\DuplicateSettingsSectionException;
use DeepWebSolutions\Framework\Settings\Schema\Exceptions\UnsupportedRestExposureException;
use DeepWebSolutions\Framework\Settings\Schema\Field\FieldType;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsField;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsPage;

use function DeepWebSolutions\Framework\Shared\Identifier\is_valid_identifier;

/**
 * Filters a descriptor's HTML attribute map to the names safe to emit into a control unescaped.
 *
 * Keeps an attribute only when its name is a plain attribute token — a letter followed by letters,
 * digits, or hyphens — and does not begin with `on`. Blocks every `on<event>` handler by prefix
 * (conservative; no standard control attribute starts with `on`), so a descriptor cannot inject an
 * executable handler nor smuggle a space-separated second attribute through a crafted key.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   array<string, scalar> $attributes Attribute map to filter.
 *
 * @return  array<string, scalar>
 */
function filter_field_attributes( array $attributes ): array {
	$filtered = array();
	foreach ( $attributes as $attribute => $value ) {
		$attribute = (string) $attribute;
		if ( 1 !== \preg_match( '/\A[a-z][a-z0-9-]*\z/i', $attribute ) || 0 === \stripos( $attribute, 'on' ) ) {
			continue;
		}

		$filtered[ $attribute ] = $value;
	}

	return $filtered;
}

/**
 * Derives the DOM id for a field control from its HTML name: each bracketed segment must be a valid
 * identifier, and the segments join on '.' ('opt[color]' becomes 'opt.color'). The separator
 * is valid in HTML ids and IDREFs but outside the identifier charset, so the derivation is injective —
 * two distinct accepted names can never derive the same id. Returns the empty string for a name
 * outside that shape — the caller emits no id rather than an unvetted one.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string $name HTML name attribute of the control.
 *
 * @return  string
 */
function field_control_id( string $name ): string {
	$segments = \explode( '[', $name );
	foreach ( $segments as $index => $segment ) {
		if ( $index > 0 ) {
			if ( ! \str_ends_with( $segment, ']' ) ) {
				return '';
			}
			$segment = \substr( $segment, 0, -1 );
		}
		if ( ! is_valid_identifier( $segment ) ) {
			return '';
		}
		$segments[ $index ] = $segment;
	}

	return \implode( '.', $segments );
}

/**
 * Resolves the DOM id for a field's control: a descriptor-supplied 'id' attribute (matched
 * case-insensitively, as the attribute filter accepts any casing) is the consumer's own id and wins
 * verbatim; otherwise the id derives from the control's HTML name via {@see field_control_id()}.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   SettingsField $field Field whose control id to resolve.
 * @param   string        $name  HTML name attribute of the control.
 *
 * @return  string
 */
function resolve_field_control_id( SettingsField $field, string $name ): string {
	foreach ( $field->attributes as $attribute => $value ) {
		if ( 0 === \strcasecmp( (string) $attribute, 'id' ) && '' !== (string) $value ) {
			return (string) $value;
		}
	}

	return namespace\field_control_id( $name );
}

/**
 * The canonical checkbox truth rule: a value is checked only when it is boolean true, the integer 1,
 * the string '1', or the string 'yes'. Everything else — false, 0, '0', 'no', '', null, 'on', any
 * other string, an array — is unchecked.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   mixed $value Value to evaluate.
 *
 * @return  bool
 */
function is_checkbox_checked( mixed $value ): bool {
	return true === $value || 1 === $value || '1' === $value || 'yes' === $value;
}

/**
 * Normalizes a checkbox value to the canonical stored representation.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   mixed $value Value to normalize.
 *
 * @return  string
 */
function normalize_checkbox_value( mixed $value ): string {
	return namespace\is_checkbox_checked( $value ) ? 'yes' : 'no';
}

/**
 * Coerces a value to a string for output; a non-scalar becomes an empty string.
 *
 * The one coercion rule for a value bound into a rendered control or handed to a WordPress or
 * WooCommerce output seam that escapes strings, applied everywhere so the renderers and builders
 * never disagree on how a non-scalar degrades.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   mixed $value Value to stringify.
 *
 * @return  string
 */
function stringify_for_output( mixed $value ): string {
	return \is_scalar( $value ) ? (string) $value : '';
}

/**
 * Stringifies a resolved options map's labels; a non-scalar label becomes an empty string.
 *
 * Applies {@see stringify_for_output()} to each label: every rendering seam hands an option label to
 * an escaping output function (esc_html()), which expects a string.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   array<array-key, mixed> $options Resolved value-to-label map.
 *
 * @return  array<array-key, string>
 */
function stringify_option_labels( array $options ): array {
	$labels = array();
	foreach ( $options as $value => $label ) {
		$labels[ $value ] = namespace\stringify_for_output( $label );
	}

	return $labels;
}

/**
 * Renders a field's visible label for a surface's label cell as an escaped HTML string: a label
 * element bound to the control's DOM id where a single control carries the accessible name, or the
 * escaped label text alone where none does — a radio group (its fieldset legend names it), a custom
 * type (its renderer owns the control markup, so no id is guaranteed), or an empty control id.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   SettingsField $field      Field whose label to render.
 * @param   string        $control_id DOM id of the field's rendered control; '' renders no association.
 *
 * @return  string
 */
function field_label_html( SettingsField $field, string $control_id ): string {
	$type = FieldType::tryFrom( $field->type );
	if ( '' === $control_id || null === $type || FieldType::Radio === $type ) {
		return \esc_html( $field->label );
	}

	return \sprintf( '<label for="%s">%s</label>', \esc_attr( $control_id ), \esc_html( $field->label ) );
}

/**
 * Renders a field's meta-box row as an escaped HTML string: the control wrapped in the shared row
 * markup, its label bound to the control's DOM id. A div, not a paragraph: a radio fieldset or the
 * description paragraph inside a p would be reparsed as invalid HTML.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   SettingsField $field      Field the row hosts.
 * @param   string        $control    Escaped HTML markup of the field's rendered control.
 * @param   string        $control_id DOM id of the field's rendered control; '' renders no label association.
 *
 * @return  string
 */
function meta_box_row_html( SettingsField $field, string $control, string $control_id ): string {
	return '<div class="dws-meta-box-field">' . namespace\field_label_html( $field, $control_id ) . '<br />' . $control . '</div>';
}

/**
 * Whether the current user may edit a field: a field with no capability is always editable, otherwise
 * the current user must hold the field's primitive capability. Object-scoped checks stay with the
 * hosting WordPress surface and stricter field sanitize/validate seams. A free function on purpose,
 * keeping the current_user_can() runtime check off the readonly SettingsField descriptor.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   SettingsField $field Field whose capability gate to evaluate.
 *
 * @return  bool
 */
function is_field_editable_by_current_user( SettingsField $field ): bool {
	return null === $field->capability || \current_user_can( $field->capability );
}

/**
 * Asserts a page declares unique section ids and page-unique field ids — the invariant a backend relies
 * on to anchor one storage group per section and to route a field id to a single option.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   SettingsPage $page Page descriptor to validate.
 *
 * @throws  DuplicateSettingsSectionException If two sections on the page share an id.
 * @throws  DuplicateSettingsFieldException If two fields on the page share an id.
 */
function assert_unique_section_and_field_ids( SettingsPage $page ): void {
	$sections = array();
	$fields   = array();
	foreach ( $page->sections as $section ) {
		if ( \array_key_exists( $section->id, $sections ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- framework-internal exception; never reaches an HTML output context unescaped.
			throw new DuplicateSettingsSectionException( "Duplicate settings section id on page: '$section->id'" );
		}
		$sections[ $section->id ] = true;

		foreach ( $section->fields as $field ) {
			if ( \array_key_exists( $field->id, $fields ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- framework-internal exception; never reaches an HTML output context unescaped.
				throw new DuplicateSettingsFieldException( "Duplicate settings field id on page: '$field->id'" );
			}
			$fields[ $field->id ] = true;
		}
	}
}

/**
 * The default sanitizer per built-in field type, each matching the WordPress sanitizer for the type's meaning.
 *
 * Applied by a processor when a field declares no sanitizer of its own, so a built-in semantic field
 * (email, url, number, text, textarea) persists a value cleaned to its type rather than the raw submission.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @return  array<string, \Closure>
 */
function wordpress_field_type_sanitizers(): array {
	return array(
		FieldType::Text->value     => static fn ( mixed $value ): string => \sanitize_text_field( (string) $value ),
		FieldType::Textarea->value => static fn ( mixed $value ): string => \sanitize_textarea_field( (string) $value ),
		FieldType::Email->value    => static fn ( mixed $value ): string => \sanitize_email( (string) $value ),
		FieldType::Url->value      => static fn ( mixed $value ): string => \esc_url_raw( (string) $value ),
		FieldType::Number->value   => static function ( mixed $value ): int|float|string {
			if ( ! \is_numeric( $value ) ) {
				return '';
			}
			$number = $value + 0;
			// Reject a non-finite float (e.g. an out-of-range exponent coerced to INF) — never a settings value.
			return \is_int( $number ) || \is_finite( $number ) ? $number : '';
		},
	);
}

/**
 * The REST schema for a built-in field's stored value: a JSON-schema type admitting both a value of the
 * field's type and the empty a grouped section row carries for an unsubmitted field.
 *
 * A section row stores every field together, where an unsubmitted field is the type's empty — 'no' for a
 * checkbox, false for another scalar field, an empty array for the multi-value field — so each scalar type
 * maps to a union that admits its stored value and empty. Only the built-in taxonomy has a faithful schema:
 * a custom field type's value domain is consumer-defined (its default may be null or a non-scalar that would
 * null the whole REST setting), so it is rejected rather than schematized loosely.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   SettingsField $field Field whose REST schema to build.
 *
 * @throws  UnsupportedRestExposureException If the field's type is outside the built-in taxonomy.
 *
 * @return  array<string, mixed>
 */
function rest_schema_for_field( SettingsField $field ): array {
	return match ( FieldType::tryFrom( $field->type ) ) {
		FieldType::Multiselect              => array(
			'type'  => 'array',
			'items' => array( 'type' => array( 'string', 'integer' ) ),
		),
		FieldType::Checkbox                 => array( 'type' => array( 'boolean', 'string', 'integer' ) ),
		FieldType::Number                   => array( 'type' => array( 'integer', 'number', 'string', 'boolean' ) ),
		FieldType::Select, FieldType::Radio => array( 'type' => array( 'string', 'integer', 'boolean' ) ),
		FieldType::Text, FieldType::Email, FieldType::Url, FieldType::Textarea => array( 'type' => array( 'string', 'boolean' ) ),
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- framework-internal exception; never reaches an HTML output context unescaped.
		default => throw new UnsupportedRestExposureException( "Settings field '$field->id' has a type outside the built-in taxonomy and cannot be exposed via REST." ),
	};
}
