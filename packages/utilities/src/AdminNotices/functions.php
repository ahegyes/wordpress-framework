<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\AdminNotices;

/**
 * Whether $id is a stable admin-notice dismissal key: a non-empty run of lowercase letters, digits,
 * underscores, and hyphens. An id outside this charset would not survive the sanitize_key() the AJAX
 * dismissal round-trip applies, leaving the notice impossible to dismiss, so every notice-id source
 * gates on this before constructing a notice.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string $id Candidate notice id.
 *
 * @return  bool
 */
function is_valid_notice_id( string $id ): bool {
	return 1 === \preg_match( '/\A[a-z0-9_-]+\z/', $id );
}
