<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Schema\ValueObjects;

use DeepWebSolutions\Framework\Settings\Schema\Exceptions\InvalidCustomFieldTypeException;

use function DeepWebSolutions\Framework\Settings\Schema\is_valid_identifier;

/**
 * Descriptor for a settings field type outside the framework taxonomy.
 *
 * Immutable carrier registering a render seam for a type token the closed FieldType enum does not
 * define. A consumer hands a map of these to the FieldRenderer and FieldProcessor primitives, which
 * consult the registry after the enum and before rejecting an unknown type. The registry is
 * render-only: saving reuses the field's own sanitize/validate seam, so this descriptor carries no
 * processing logic. The type token is validated against the identifier charset at construction.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final readonly class CustomFieldType {
	// region FIELDS AND CONSTANTS

	/**
	 * Renderer producing the type's escaped HTML control. Signature `(SettingsField $field, mixed $value, string $name): string`.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     \Closure
	 */
	public \Closure $render;

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string   $type Type token the field declares; a lowercase identifier outside the FieldType enum.
	 * @param   callable $render Renderer for the type's control; stored as a Closure.
	 *
	 * @throws  InvalidCustomFieldTypeException If $type does not match the identifier charset.
	 */
	public function __construct(
		public string $type,
		callable $render,
	) {
		if ( ! is_valid_identifier( $type ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- framework-internal exception; never reaches an HTML output context unescaped.
			throw new InvalidCustomFieldTypeException( "Invalid custom field type: '$type'" );
		}

		$this->render = \Closure::fromCallable( $render );
	}

	// endregion
}
