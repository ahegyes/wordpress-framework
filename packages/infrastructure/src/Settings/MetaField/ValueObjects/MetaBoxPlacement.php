<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\MetaField\ValueObjects;

use DeepWebSolutions\Framework\Shared\ValueObject\AbstractValueObject;

/**
 * Value object for a meta box's WordPress placement.
 *
 * The add_meta_box() triple — screen, context, priority — plus an optional capability that overrides a
 * registrar's default object capability for the box. Carried alongside a {@see FieldGroup} by the
 * surfaces that register WordPress meta boxes.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final readonly class MetaBoxPlacement extends AbstractValueObject {
	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string  $screen Screen or object type the box attaches to.
	 * @param   string  $context WordPress meta-box context (normal, side, advanced).
	 * @param   string  $priority WordPress meta-box priority (high, core, default, low).
	 * @param   ?string $capability Capability overriding the registrar's default object capability for the box; null keeps the default.
	 */
	public function __construct(
		public string $screen,
		public string $context,
		public string $priority,
		public ?string $capability = null,
	) {}

	// endregion
}
