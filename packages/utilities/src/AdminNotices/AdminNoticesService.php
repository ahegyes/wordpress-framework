<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\AdminNotices;

use DeepWebSolutions\Framework\Utilities\AdminNotices\ValueObjects\AdminNotice;

/**
 * Collects admin notices and renders them when {@see self::render_notices()} is invoked.
 *
 * Components add notices via {@see self::add_notice()}; consumers hook the service to
 * the WordPress `admin_notices` action (typically through HooksService in their plugin
 * component's register_hooks()). Renders use wp_admin_notice() (WP 6.4+) when available
 * with a wp_kses_post()-wrapped echo fallback for older WordPress sites.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class AdminNoticesService {
	// region FIELDS AND CONSTANTS

	/**
	 * Pending notices indexed by ID.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     array<string, AdminNotice>
	 */
	private(set) array $notices = array();

	// endregion

	// region METHODS

	/**
	 * Queue a notice for rendering on the next render_notices() call.
	 *
	 * If a notice with the same ID is already queued, it is replaced.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   AdminNotice $notice Notice to queue.
	 */
	public function add_notice( AdminNotice $notice ): void {
		$this->notices[ $notice->id ] = $notice;
	}

	/**
	 * Remove a previously queued notice by ID.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $id Notice ID to remove.
	 *
	 * @return  bool True if a queued notice was removed, false if none existed under $id.
	 */
	public function remove_notice( string $id ): bool {
		if ( ! isset( $this->notices[ $id ] ) ) {
			return false;
		}
		unset( $this->notices[ $id ] );
		return true;
	}

	/**
	 * Render all queued notices the current user has the capability to see.
	 *
	 * Hook this method onto the `admin_notices` action via HooksService or directly.
	 * Notices not visible to the current user are skipped (left in the queue).
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function render_notices(): void {
		foreach ( $this->notices as $notice ) {
			if ( ! \current_user_can( $notice->capability ) ) {
				continue;
			}
			$this->render_one( $notice );
		}
	}

	// endregion

	// region HELPERS

	/**
	 * Render a single notice using wp_admin_notice() when available, falling back to a
	 * sanitized echo for older WordPress versions.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   AdminNotice $notice Notice to render.
	 */
	private function render_one( AdminNotice $notice ): void {
		$attributes = array(
			'id'             => 'dws-notice-' . $notice->id,
			'type'           => $notice->type->value,
			'dismissible'    => $notice->dismissible,
			'paragraph_wrap' => true,
		);

		if ( \function_exists( 'wp_admin_notice' ) ) {
			\wp_admin_notice( $notice->message, $attributes );
			return;
		}

		$classes = 'notice notice-' . $notice->type->value;
		if ( $notice->dismissible ) {
			$classes .= ' is-dismissible';
		}
		printf(
			'<div id="%1$s" class="%2$s"><p>%3$s</p></div>',
			\esc_attr( $attributes['id'] ),
			\esc_attr( $classes ),
			\wp_kses_post( $notice->message ),
		);
	}

	// endregion
}
