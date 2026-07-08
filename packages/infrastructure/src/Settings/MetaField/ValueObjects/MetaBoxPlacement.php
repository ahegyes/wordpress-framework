<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\MetaField\ValueObjects;

use DeepWebSolutions\Framework\Settings\MetaField\Exceptions\InvalidMetaBoxPlacementException;
use DeepWebSolutions\Framework\Shared\ValueObject\AbstractValueObject;

use function DeepWebSolutions\Framework\Shared\Identifier\is_valid_identifier;

/**
 * Value object for a meta box's WordPress placement.
 *
 * The add_meta_box() triple — screen, context, priority — plus an optional capability that overrides a
 * registrar's default object capability for the box. Carried alongside a {@see FieldGroup} by the
 * surfaces that register WordPress meta boxes. The screen is interpolated into hook names, so it must
 * match the shared identifier charset; context and priority are validated against WordPress' closed sets.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final readonly class MetaBoxPlacement extends AbstractValueObject {
	// region FIELDS AND CONSTANTS

	/**
	 * The meta-box contexts WordPress recognizes on the edit screens.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     list<string>
	 */
	protected const CONTEXTS = array( 'normal', 'side', 'advanced' );

	/**
	 * The meta-box priorities WordPress recognizes.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     list<string>
	 */
	protected const PRIORITIES = array( 'high', 'core', 'default', 'low' );

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string  $screen Screen or object type the box attaches to; a lowercase token matching the shared identifier charset.
	 * @param   string  $context WordPress meta-box context (normal, side, advanced).
	 * @param   string  $priority WordPress meta-box priority (high, core, default, low).
	 * @param   ?string $capability Capability overriding the registrar's default object capability for the box; null keeps the default.
	 *
	 * @throws  InvalidMetaBoxPlacementException If $screen does not match the identifier charset, or $context or $priority is outside its closed set.
	 */
	public function __construct(
		public string $screen,
		public string $context,
		public string $priority,
		public ?string $capability = null,
	) {
		// The surfaces interpolate the screen into hook names (add_meta_boxes_{screen}, save_post_{screen}),
		// so it is held to the shared identifier charset rather than WordPress' looser sanitize_key set.
		if ( ! is_valid_identifier( $screen ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- framework-internal exception; never reaches an HTML output context unescaped.
			throw new InvalidMetaBoxPlacementException( "invalid screen: '$screen'. Use an identifier of a lowercase letter followed by lowercase a-z, 0-9, _, - so hook names derived from it stay well-formed." );
		}
		if ( ! \in_array( $context, self::CONTEXTS, true ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- framework-internal exception; never reaches an HTML output context unescaped.
			throw new InvalidMetaBoxPlacementException( "invalid context '$context'; expected one of 'normal', 'side', 'advanced'." );
		}
		if ( ! \in_array( $priority, self::PRIORITIES, true ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- framework-internal exception; never reaches an HTML output context unescaped.
			throw new InvalidMetaBoxPlacementException( "invalid priority '$priority'; expected one of 'high', 'core', 'default', 'low'." );
		}
	}

	// endregion

	// region GETTERS

	/**
	 * The capability gating the box: the placement's override, or the object's own 'edit_post' meta
	 * capability when none is set.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  string
	 */
	public function get_capability(): string {
		return $this->capability ?? 'edit_post';
	}

	// endregion
}
