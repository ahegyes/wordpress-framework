<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\AdminNotices;

use DeepWebSolutions\Framework\Utilities\AdminNotices\ValueObjects\AdminNotice;
use DeepWebSolutions\Framework\Utilities\Storage\MemoryStore;

/**
 * Collects admin notices across one or more named stores (in-memory, wp_options, user_meta) and
 * renders them when {@see self::render_notices()} is invoked. Each notice is gated by the current
 * user's capability and, when persistent, by a per-user dismissal record; a non-persistent notice is
 * consumed (removed from its store) after it renders once. Renders use wp_admin_notice() (WP 6.4+)
 * with a wp_kses_post()-wrapped echo fallback for older WordPress sites.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class AdminNoticesService {
	// region FIELDS AND CONSTANTS

	/**
	 * Name of the in-memory store registered by default.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const DEFAULT_STORE = 'memory';

	/**
	 * Registered notice stores, indexed by name.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     array<string, NoticeStore>
	 */
	private(set) array $stores;

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<string, NoticeStore>|null $stores      Stores to register, indexed by name. Null registers a single in-memory store under DEFAULT_STORE; an empty array registers none.
	 * @param   DismissedNoticesTracker|null    $dismissals  Per-user dismissal record. When null, persistent notices are never suppressed.
	 */
	public function __construct(
		?array $stores = null,
		private ?DismissedNoticesTracker $dismissals = null,
	) {
		$this->stores = $stores ?? array( self::DEFAULT_STORE => new NoticeStore( new MemoryStore() ) );
	}

	// endregion

	// region METHODS

	/**
	 * Queue a notice in the named store. Replaces any notice already stored under the same ID there.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   AdminNotice $notice Notice to queue.
	 * @param   string      $store  Name of the store to queue it in. Defaults to DEFAULT_STORE.
	 */
	public function add_notice( AdminNotice $notice, string $store = self::DEFAULT_STORE ): void {
		if ( ! isset( $this->stores[ $store ] ) ) {
			\_doing_it_wrong(
				__METHOD__,
				\sprintf( 'Unknown notice store "%s"; the notice was not queued.', $store ),
				'2.0.0'
			);
			return;
		}

		$this->stores[ $store ]->add( $notice );
	}

	/**
	 * Remove a queued notice by ID, from the named store or — when none is given — from every store.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string      $id    ID of the notice to remove.
	 * @param   string|null $store Store to remove it from, or null to search every store.
	 *
	 * @return  bool True if a notice was removed from any store, false otherwise.
	 */
	public function remove_notice( string $id, ?string $store = null ): bool {
		if ( null !== $store ) {
			return isset( $this->stores[ $store ] ) && $this->stores[ $store ]->remove( $id );
		}

		$removed = false;
		foreach ( $this->stores as $notice_store ) {
			if ( $notice_store->remove( $id ) ) {
				$removed = true;
			}
		}
		return $removed;
	}

	/**
	 * Render every queued notice the current user may see. Hook this onto the `admin_notices` action.
	 *
	 * A notice the current user lacks the capability for is skipped and left queued. A persistent,
	 * dismissible notice the user has dismissed is suppressed (left in its store). A non-persistent
	 * notice is removed from its store once rendered, so it shows at most once.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function render_notices(): void {
		foreach ( $this->stores as $store ) {
			foreach ( $store->get_all() as $notice ) {
				if ( ! \current_user_can( $notice->capability ) ) {
					continue;
				}

				$suppressed = $notice->is_persistent && $notice->dismissible
					&& true === $this->dismissals?->is_dismissed( $notice->id );
				if ( ! $suppressed ) {
					$this->render_one( $notice );
				}

				if ( ! $notice->is_persistent ) {
					$store->remove( $notice->id );
				}
			}
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
