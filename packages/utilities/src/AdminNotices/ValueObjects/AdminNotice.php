<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\AdminNotices\ValueObjects;

/**
 * Value object representing a single WordPress admin notice.
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
	 * @param   string     $id          Unique identifier (used for dismissal tracking and removal).
	 * @param   string     $message     Notice message (inline HTML allowed; sanitized and paragraph-wrapped at render time).
	 * @param   NoticeType $type        Severity level. Defaults to NoticeType::Info.
	 * @param   bool       $dismissible Whether the notice shows a dismiss button. Defaults to true.
	 * @param   string     $capability  Capability required to see the notice. Defaults to 'manage_options'.
	 */
	public function __construct(
		public string $id,
		public string $message,
		public NoticeType $type = NoticeType::Info,
		public bool $dismissible = true,
		public string $capability = 'manage_options',
	) {}

	// endregion
}
