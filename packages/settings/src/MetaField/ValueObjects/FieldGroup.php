<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\MetaField\ValueObjects;

use DeepWebSolutions\Framework\Settings\MetaField\Exceptions\InvalidFieldGroupException;

use function DeepWebSolutions\Framework\Shared\Identifier\is_valid_identifier;

/**
 * Descriptor for a surface-agnostic group of object fields.
 *
 * Carries what every metadata surface shares: an id unique on its surface, a heading, and a provider
 * closure that builds the group's fields per object at render time — so a group can present per-object,
 * state-conditional fields. Optional render and save closures are an escape hatch for bespoke per-object
 * UI and persistence. Surface placement (a meta box's screen, a term's taxonomy) rides a separate
 * per-surface descriptor.
 *
 * The group carries no meta-key prefix: each field stores under its own meta_key override or bare id,
 * so prefixing storage keys against collisions in the shared meta table is the consumer's per-field
 * responsibility — the form engine rejects duplicate storage keys only within the group.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final readonly class FieldGroup {
	// region FIELDS AND CONSTANTS

	/**
	 * Builds the fields for a given object id: signature `(int $object_id): list<SettingsField>`.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     \Closure
	 */
	public \Closure $fields_provider;

	/**
	 * Bespoke renderer overriding default field rendering, with signature `(int $object_id): string`; null uses the default renderer.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     ?\Closure
	 */
	public ?\Closure $render;

	/**
	 * Bespoke save handler overriding default persistence, with signature `(int $object_id): void`; null uses the default save path.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     ?\Closure
	 */
	public ?\Closure $save;

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string    $id Group identifier, unique on its surface; a lowercase token matching the id charset.
	 * @param   string    $title Human-readable group heading.
	 * @param   callable  $fields_provider Builds the fields for an object id; stored as a Closure.
	 * @param   ?callable $render Bespoke renderer; stored as a Closure.
	 * @param   ?callable $save Bespoke save handler; stored as a Closure.
	 *
	 * @throws  InvalidFieldGroupException If $id does not match the id charset.
	 */
	public function __construct(
		public string $id,
		public string $title,
		callable $fields_provider,
		?callable $render = null,
		?callable $save = null,
	) {
		if ( ! is_valid_identifier( $id ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- framework-internal exception; never reaches an HTML output context unescaped.
			throw new InvalidFieldGroupException( "Invalid field group id: '$id'" );
		}

		$this->fields_provider = \Closure::fromCallable( $fields_provider );
		$this->render          = null !== $render ? \Closure::fromCallable( $render ) : null;
		$this->save            = null !== $save ? \Closure::fromCallable( $save ) : null;
	}

	// endregion
}
