<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\AdminNotices\ValueObjects;

use DeepWebSolutions\Framework\Utilities\AdminNotices\Exceptions\InvalidAdminNoticeException;
use DeepWebSolutions\Framework\Utilities\AdminNotices\NoticeType;

use function DeepWebSolutions\Framework\Utilities\AdminNotices\is_valid_notice_id;

/**
 * Descriptor for a single WordPress admin notice.
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
	 * @param   string     $id             Unique identifier (used for dismissal tracking, storage keying, and removal). Must be sanitize_key-stable (lowercase a-z, 0-9, _, -) so AJAX dismissal round-trips.
	 * @param   string     $message        Notice message (inline HTML allowed; sanitized and paragraph-wrapped at render time).
	 * @param   NoticeType $type           Severity level. Defaults to NoticeType::Info.
	 * @param   bool       $is_dismissible Whether the notice shows a dismiss button. Defaults to true.
	 * @param   bool       $is_persistent  Whether the notice recurs across renders (true) or is consumed after rendering once (false). Defaults to false.
	 * @param   string     $capability     Capability required to see the notice. Defaults to 'manage_options'.
	 *
	 * @throws  InvalidAdminNoticeException If $id is not sanitize_key-stable.
	 */
	public function __construct(
		public string $id,
		public string $message,
		public NoticeType $type = NoticeType::Info,
		public bool $is_dismissible = true,
		public bool $is_persistent = false,
		public string $capability = 'manage_options',
	) {
		if ( ! is_valid_notice_id( $id ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- framework-internal exception; never reaches an HTML output context unescaped.
			throw new InvalidAdminNoticeException( "Invalid admin notice id: '$id'. Use a sanitize_key-stable id (lowercase a-z, 0-9, _, -) so AJAX dismissal round-trips." );
		}
	}

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
			'id'             => $this->id,
			'message'        => $this->message,
			'type'           => $this->type->value,
			'is_dismissible' => $this->is_dismissible,
			'is_persistent'  => $this->is_persistent,
			'capability'     => $this->capability,
		);
	}

	// endregion

	// region FACTORY METHODS

	/**
	 * Rehydrates a notice from its stored array form, falling back to safe defaults for any missing or
	 * wrong-typed field other than the id, so a corrupt row degrades to a benign notice. The id is still
	 * validated by the constructor, so a missing or unstable id throws and a caller rehydrating untrusted
	 * stored data must guard against it.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<string, mixed> $data Stored array, as produced by {@see self::to_array()}.
	 *
	 * @throws  InvalidAdminNoticeException If the stored id is missing, non-string, or not sanitize_key-stable.
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
			is_dismissible: \is_bool( $data['is_dismissible'] ?? null ) ? $data['is_dismissible'] : true,
			is_persistent: \is_bool( $data['is_persistent'] ?? null ) ? $data['is_persistent'] : false,
			capability: \is_string( $data['capability'] ?? null ) ? $data['capability'] : 'manage_options',
		);
	}

	// endregion
}
