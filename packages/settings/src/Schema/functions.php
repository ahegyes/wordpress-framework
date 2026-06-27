<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Schema;

use DeepWebSolutions\Framework\Settings\Schema\Exceptions\DuplicateSettingsFieldException;
use DeepWebSolutions\Framework\Settings\Schema\Exceptions\DuplicateSettingsSectionException;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsField;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsPage;

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
 * Whether a string is a valid settings identifier: a lowercase letter followed by lowercase letters,
 * digits, underscores, or hyphens.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string $identifier Identifier to validate.
 *
 * @return  bool
 */
function is_valid_identifier( string $identifier ): bool {
	return 1 === \preg_match( '/\A[a-z][a-z0-9_-]*\z/', $identifier );
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
 * Whether the current user may edit a field: a field with no capability is always editable, otherwise
 * the current user must hold the field's capability. A free function on purpose, keeping the
 * current_user_can() runtime check off the readonly SettingsField descriptor.
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
