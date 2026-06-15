<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\ValueObjects;

use DeepWebSolutions\Framework\Settings\Exceptions\InvalidObjectMetaBoxException;

/**
 * Declarative description of a per-entity meta box (object fields).
 *
 * Drives the object-field backend: the box attaches to an object screen and its
 * fields are built per object at render time by the provider closure — so a box
 * can present per-order, per-gateway, state-conditional fields. Optional render
 * and save closures are an escape hatch for bespoke per-object UI and persistence.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final readonly class ObjectMetaBox {
	// region FIELDS AND CONSTANTS

	/**
	 * Meta-box id charset: a lowercase token safe to emit into WordPress meta-box markup unescaped.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	private const ID_PATTERN = '/\A[a-z][a-z0-9_-]*\z/';

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
	 * Bespoke renderer overriding default field rendering; null uses the default renderer.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     ?\Closure
	 */
	public ?\Closure $render;

	/**
	 * Bespoke save handler overriding default persistence; null uses the default save path.
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
	 * @param   string $id Meta-box identifier, unique on its screen.
	 * @param   string $title Meta-box heading.
	 * @param   string $screen Screen or object type the box attaches to.
	 * @param   string $context WordPress meta-box context (normal, side, advanced).
	 * @param   string $priority WordPress meta-box priority (default, high, low).
	 * @param   callable $fields_provider Builds the fields for an object id; stored as a Closure.
	 * @param   ?callable $render Bespoke renderer; stored as a Closure.
	 * @param   ?callable $save Bespoke save handler; stored as a Closure.
	 *
	 * @throws  InvalidObjectMetaBoxException If $id does not match the meta-box id charset.
	 */
	public function __construct(
		public string $id,
		public string $title,
		public string $screen,
		public string $context,
		public string $priority,
		callable $fields_provider,
		?callable $render = null,
		?callable $save = null,
	) {
		if ( 1 !== \preg_match( self::ID_PATTERN, $id ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- framework-internal exception; never reaches an HTML output context unescaped.
			throw new InvalidObjectMetaBoxException( "Invalid object meta-box id: '$id'" );
		}

		$this->fields_provider = \Closure::fromCallable( $fields_provider );
		$this->render          = null !== $render ? \Closure::fromCallable( $render ) : null;
		$this->save            = null !== $save ? \Closure::fromCallable( $save ) : null;
	}

	// endregion
}
