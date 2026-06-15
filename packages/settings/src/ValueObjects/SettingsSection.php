<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\ValueObjects;

/**
 * Declarative description of a settings section: a titled group of fields.
 *
 * A section is the unit of storage for the WordPress options backend — its
 * identifier doubles as the persisted option-group key segment — and the unit of
 * registration for the Settings API. It carries no capability of its own: access
 * is gated by the owning page's capability, optionally narrowed per field.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final readonly class SettingsSection {
	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $id Section identifier, unique within its page.
	 * @param   string $title Human-readable section heading.
	 * @param   list<SettingsField> $fields Fields belonging to the section, in display order.
	 */
	public function __construct(
		public string $id,
		public string $title,
		public array $fields,
	) {}

	// endregion
}
