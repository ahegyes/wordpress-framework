<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\AdminNotices;

use DeepWebSolutions\Framework\Utilities\Storage\UserMetaStore;

/**
 * Records, per user, which admin notices a user has dismissed, so a dismissed recurring notice is
 * never shown to that user again. Backed by a per-user {@see UserMetaStore}, so dismissals always
 * target the current user. Only persistent notices are gated by this tracker: a one-shot notice is
 * consumed after its single render, so its dismissal would never be consulted.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final readonly class DismissedNoticesTracker {
	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   UserMetaStore<bool> $store Per-user backend the dismissed IDs are recorded in.
	 */
	public function __construct(
		private UserMetaStore $store,
	) {}

	// endregion

	// region GETTERS

	/**
	 * Whether the current user has dismissed the notice with the given ID.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $id Notice ID to check.
	 *
	 * @return  bool
	 */
	public function is_dismissed( string $id ): bool {
		return $this->store->has( $id );
	}

	// endregion

	// region METHODS

	/**
	 * Record that the current user has dismissed the notice with the given ID.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $id Notice ID to mark dismissed.
	 */
	public function dismiss( string $id ): void {
		$this->store->set( $id, true );
	}

	// endregion
}
