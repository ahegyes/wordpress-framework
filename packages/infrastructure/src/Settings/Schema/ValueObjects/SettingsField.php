<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Schema\ValueObjects;

use DeepWebSolutions\Framework\Settings\Schema\Exceptions\InvalidSettingsFieldException;
use DeepWebSolutions\Framework\Settings\Schema\Options\OptionsProviderInterface;

use function DeepWebSolutions\Framework\Shared\Identifier\is_valid_identifier;

/**
 * Descriptor for a single settings field, storage- and UI-agnostic.
 *
 * Immutable carrier of everything a backend needs to register, render, sanitize,
 * and validate one field: identifier, type token, presentation metadata, an
 * optional per-field capability, and the sanitize/validate seam. The type is a
 * string token resolved against the framework field-type taxonomy at processing
 * time, so an unknown type surfaces where the field is rendered or processed,
 * not at construction.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final readonly class SettingsField {
	// region FIELDS AND CONSTANTS

	/**
	 * Sanitizer applied to the submitted value before validation; null leaves the value untouched.
	 * Signature `(mixed $value): mixed` — a value transformer whose return is carried forward.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     ?\Closure
	 */
	public ?\Closure $sanitize;

	/**
	 * Validator applied to the sanitized value; null accepts any value. Signature `(mixed $value): bool`
	 * — a boolean gate whose return counts only for truthiness, so a sanitizer-style validator returning
	 * a cleaned (truthy) string never fails validation.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     ?\Closure
	 */
	public ?\Closure $validate;

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string                                                    $id Page-unique field identifier; a lowercase token matching the field-id charset.
	 * @param   string                                                    $type Field-type token resolved against the framework taxonomy when rendered or processed: a `Field\FieldType` value such as `FieldType::Text->value`, or a registered `CustomFieldType` token.
	 * @param   string                                                    $label Human-readable field label.
	 * @param   mixed                                                     $default_value Default value used when nothing is stored.
	 * @param   ?callable                                                 $sanitize Sanitizer for the submitted value; stored as a Closure. Signature `(mixed $value): mixed` — returns the transformed value.
	 * @param   ?callable                                                 $validate Validator for the sanitized value; stored as a Closure. Signature `(mixed $value): bool` — the return counts only for truthiness, so a returned (truthy) string never fails validation.
	 * @param   ?string                                                   $capability Primitive capability required to edit the field; null inherits the section/page capability. Object-scoped checks belong to the hosting WordPress surface and the field sanitize/validate seam.
	 * @param   bool                                                      $show_in_rest Whether the field is exposed via REST where the backend supports it.
	 * @param   bool                                                      $autoload Whether the field's stored value should autoload on every request; defaults to off.
	 * @param   ?int                                                      $position Sort position within the section; null keeps declaration order.
	 * @param   array<array-key, mixed>|\Closure|OptionsProviderInterface $options Option set for choice-typed fields: a literal array, a Closure (not a bare callable, so an array is always the option set), or a provider; labels are stringified at render.
	 * @param   array<string, scalar>                                     $attributes Extra HTML attributes passed through to the rendered control.
	 * @param   ?string                                                   $meta_key Object-field storage key; may be underscore-prefixed, and null for option settings.
	 * @param   ?string                                                   $description Help text rendered beneath the control; null renders none.
	 *
	 * @throws  InvalidSettingsFieldException If $id does not match the field-id charset.
	 */
	public function __construct(
		public string $id,
		public string $type,
		public string $label,
		public mixed $default_value = null,
		?callable $sanitize = null,
		?callable $validate = null,
		public ?string $capability = null,
		public bool $show_in_rest = false,
		public bool $autoload = false,
		public ?int $position = null,
		public array|\Closure|OptionsProviderInterface $options = array(),
		public array $attributes = array(),
		public ?string $meta_key = null,
		public ?string $description = null,
	) {
		if ( ! is_valid_identifier( $id ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- framework-internal exception; never reaches an HTML output context unescaped.
			throw new InvalidSettingsFieldException( "Invalid settings field id: '$id'" );
		}

		$this->sanitize = null !== $sanitize ? \Closure::fromCallable( $sanitize ) : null;
		$this->validate = null !== $validate ? \Closure::fromCallable( $validate ) : null;
	}

	// endregion
}
