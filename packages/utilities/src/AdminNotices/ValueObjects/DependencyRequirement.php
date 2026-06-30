<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\AdminNotices\ValueObjects;

use DeepWebSolutions\Framework\Core\Conditional\ConditionalInterface;
use DeepWebSolutions\Framework\Utilities\AdminNotices\Exceptions\InvalidAdminNoticeException;
use DeepWebSolutions\Framework\Utilities\AdminNotices\NoticeType;

use function DeepWebSolutions\Framework\Utilities\AdminNotices\is_valid_notice_id;

/**
 * Descriptor for a dependency a plugin declares for missing-dependency admin notices: the conditional
 * that decides whether it is satisfied, a human-readable label, and whether it is required (blocking)
 * or optional (recommended). The getters derive the notice's identity and presentation purely from
 * those fields, so {@see \DeepWebSolutions\Framework\Utilities\AdminNotices\DependencyAdminNoticeRenderer}
 * owns only the translatable message and the queueing.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final readonly class DependencyRequirement {
	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   ConditionalInterface $conditional Conditional whose unmet state triggers a notice.
	 * @param   string               $label       Human-readable dependency name (e.g. "WooCommerce").
	 * @param   bool                 $required    Whether the dependency is required (blocking) rather than optional. Defaults to true.
	 * @param   string|null          $id          Explicit notice ID (must be sanitize_key-stable so AJAX dismissal round-trips); null derives a stable one from the label. Defaults to null.
	 *
	 * @throws  InvalidAdminNoticeException If an explicit $id is not sanitize_key-stable.
	 */
	public function __construct(
		public ConditionalInterface $conditional,
		public string $label,
		public bool $required = true,
		public ?string $id = null,
	) {
		if ( null !== $this->id && ! is_valid_notice_id( $this->id ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- framework-internal exception; never reaches an HTML output context unescaped.
			throw new InvalidAdminNoticeException( "Invalid dependency notice id: '$this->id'. Use a sanitize_key-stable id (lowercase a-z, 0-9, _, -) so AJAX dismissal round-trips." );
		}
	}

	// endregion

	// region GETTERS

	/**
	 * Returns the notice ID: the explicit ID when given, otherwise a sanitize_key-stable slug derived
	 * from the label and prefixed with `dep_`.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  string
	 */
	public function get_notice_id(): string {
		if ( null !== $this->id ) {
			return $this->id;
		}

		// A normal label keeps a readable 'dep_<slug>' id; only a label that sanitizes to an empty slug
		// (all punctuation, say) falls back to a hash of the full label, so two such degenerate labels do
		// not collapse onto one shared 'dep_' id and silently drop a notice.
		$slug = \trim( (string) \preg_replace( '/[^a-z0-9_]+/', '_', \strtolower( $this->label ) ), '_' );

		return 'dep_' . ( '' === $slug ? \substr( \md5( $this->label ), 0, 12 ) : $slug );
	}

	/**
	 * Returns the notice severity: an error for a required dependency, a warning for an optional one.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  NoticeType
	 */
	public function get_notice_type(): NoticeType {
		return $this->required ? NoticeType::Error : NoticeType::Warning;
	}

	/**
	 * Whether the notice is dismissible. Only an optional dependency is dismissible; a required one
	 * recurs until it is satisfied.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  bool
	 */
	public function is_dismissible(): bool {
		return ! $this->required;
	}

	/**
	 * Whether the notice is persistent (recurs across requests with per-user dismissal). Only an
	 * optional dependency is persistent; a required one is re-queued every request by the renderer.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  bool
	 */
	public function is_persistent(): bool {
		return ! $this->required;
	}

	// endregion
}
