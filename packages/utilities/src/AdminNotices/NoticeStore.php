<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\AdminNotices;

use DeepWebSolutions\Framework\Utilities\AdminNotices\ValueObjects\AdminNotice;
use DeepWebSolutions\Framework\Storage\KeyValueStoreInterface;

/**
 * Persists admin notices through any {@see KeyValueStoreInterface} backend (in-memory, wp_options,
 * or user_meta), keyed by notice ID. Notices are stored as plain arrays via {@see AdminNotice::to_array()};
 * a stored value is rehydrated only when it is an array whose 'id' matches its storage key and whose
 * 'message' is a string, so a corrupt or foreign row is skipped instead of fataling.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final readonly class NoticeStore {
	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   KeyValueStoreInterface<array<string, mixed>> $store Backend the notices are persisted through.
	 */
	public function __construct(
		protected KeyValueStoreInterface $store,
	) {}

	// endregion

	// region METHODS

	/**
	 * Persist a notice under its ID. Overwrites any notice already stored under the same ID.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   AdminNotice $notice Notice to persist.
	 */
	public function add( AdminNotice $notice ): void {
		$this->store->set( $notice->id, $notice->to_array() );
	}

	/**
	 * Retrieve the notice stored under the given ID, or null when none is stored or the row is corrupt.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $id Notice ID to look up.
	 *
	 * @return  AdminNotice|null
	 */
	public function get( string $id ): ?AdminNotice {
		return $this->from_row( $id, $this->store->get( $id ) );
	}

	/**
	 * Check whether an entry is stored under the given ID.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $id Notice ID to check.
	 *
	 * @return  bool
	 */
	public function has( string $id ): bool {
		return $this->store->has( $id );
	}

	/**
	 * Remove the notice stored under the given ID.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $id Notice ID to remove.
	 *
	 * @return  bool True if an entry was removed, false if none existed under the ID.
	 */
	public function remove( string $id ): bool {
		return $this->store->delete( $id );
	}

	/**
	 * Return all stored notices, keyed by ID. Corrupt rows are skipped.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  array<string, AdminNotice>
	 */
	public function get_all(): array {
		$notices = array();
		foreach ( $this->store->get_all() as $key => $row ) {
			$notice = $this->from_row( (string) $key, $row );
			if ( null !== $notice ) {
				$notices[ $notice->id ] = $notice;
			}
		}
		return $notices;
	}

	/**
	 * Remove every stored notice.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function clear(): void {
		$this->store->clear();
	}

	// endregion

	// region HELPERS

	/**
	 * Rehydrate a stored row into a notice, or null when the row is not a well-formed notice stored
	 * under its own ID (non-array, missing/non-string id or message, or an id that does not match its key).
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $key Storage key the row was retrieved under.
	 * @param   mixed  $row Raw stored value.
	 *
	 * @return  AdminNotice|null
	 */
	protected function from_row( string $key, mixed $row ): ?AdminNotice {
		if ( ! \is_array( $row ) ) {
			return null;
		}
		$id = $row['id'] ?? null;
		if ( ! \is_string( $id ) || '' === $id || $id !== $key ) {
			return null;
		}
		if ( ! \is_string( $row['message'] ?? null ) ) {
			return null;
		}
		return AdminNotice::from_array( $row );
	}

	// endregion
}
