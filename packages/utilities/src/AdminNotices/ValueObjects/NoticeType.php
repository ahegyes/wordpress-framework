<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\AdminNotices\ValueObjects;

/**
 * Severity level for an admin notice.
 * Maps to WordPress's CSS classes used by core's wp_admin_notice().
 *
 * @since   2.0.0
 * @version 2.0.0
 */
enum NoticeType: string {
	case Info    = 'info';
	case Success = 'success';
	case Warning = 'warning';
	case Error   = 'error';
}
