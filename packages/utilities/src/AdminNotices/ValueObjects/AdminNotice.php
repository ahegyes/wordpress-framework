<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\AdminNotices\ValueObjects;

/**
 * Value object representing a single WordPress admin notice.
 *
 * The $is_persistent flag governs post-render retention in a persistent store: a non-persistent
 * notice is consumed (removed) after it renders once, a persistent one recurs until dismissed or
 * removed. {@see self::to_array()} / {@see self::from_array()} persist a notice as a plain array so
 * a stored notice survives per-plugin php-scoping, where a serialized object would carry a scoped
 * class name that breaks on rehydration after a prefix change.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final readonly class AdminNotice {
	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string     $id            Unique identifier (used for dismissal tracking, storage keying, and removal).
	 * @param   string     $message       Notice message (inline HTML allowed; sanitized and paragraph-wrapped at render time).
	 * @param   NoticeType $type          Severity level. Defaults to NoticeType::Info.
	 * @param   bool       $dismissible   Whether the notice shows a dismiss button. Defaults to true.
	 * @param   bool       $is_persistent Whether the notice recurs across renders (true) or is consumed after rendering once (false). Defaults to false.
	 * @param   string     $capability    Capability required to see the notice. Defaults to 'manage_options'.
	 */
	public function __construct(
		public string $id,
		public string $message,
		public NoticeType $type = NoticeType::Info,
		public bool $dismissible = true,
		public bool $is_persistent = false,
		public string $capability = 'manage_options',
	) {}

	// endregion

	// region METHODS

	/**
	 * Returns the notice as a plain array suitable for storage in wp_options or user_meta.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'id'            => $this->id,
			'message'       => $this->message,
			'type'          => $this->type->value,
			'dismissible'   => $this->dismissible,
			'is_persistent' => $this->is_persistent,
			'capability'    => $this->capability,
		);
	}

	// endregion

	// region FACTORY METHODS

	/**
	 * Rehydrates a notice from its stored array form, falling back to safe defaults for any
	 * missing or wrong-typed field so a corrupt row degrades to a benign notice instead of fataling.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<string, mixed> $data Stored array, as produced by {@see self::to_array()}.
	 *
	 * @return  self
	 */
	public static function from_array( array $data ): self {
		$type = ( isset( $data['type'] ) && \is_string( $data['type'] ) )
			? ( NoticeType::tryFrom( $data['type'] ) ?? NoticeType::Info )
			: NoticeType::Info;

		return new self(
			id: \is_string( $data['id'] ?? null ) ? $data['id'] : '',
			message: \is_string( $data['message'] ?? null ) ? $data['message'] : '',
			type: $type,
			dismissible: \is_bool( $data['dismissible'] ?? null ) ? $data['dismissible'] : true,
			is_persistent: \is_bool( $data['is_persistent'] ?? null ) ? $data['is_persistent'] : false,
			capability: \is_string( $data['capability'] ?? null ) ? $data['capability'] : 'manage_options',
		);
	}

	// endregion
}
