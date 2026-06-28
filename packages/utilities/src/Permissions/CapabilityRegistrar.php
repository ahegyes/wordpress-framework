<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Permissions;

/**
 * Grants, reconciles, and revokes role capabilities from a plain role-to-capabilities map.
 *
 * A plugin's installer drives this: grant on install, reconcile on update (adding the new map and
 * removing any capability dropped since the prior version, so a capability moved to another role is
 * cleaned up too), and revoke on uninstall. Every operation is idempotent and skips an unknown role.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class CapabilityRegistrar {
	// region METHODS

	/**
	 * Adds each capability to its role, skipping one already granted so the option is written only on a
	 * real change. Reads the role's stored capabilities rather than the filterable has_cap(), so a
	 * capability only virtually granted by a filter is still persisted.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<string, list<string>> $role_caps Capabilities to add, keyed by role slug.
	 */
	public function grant( array $role_caps ): void {
		foreach ( $role_caps as $role_slug => $caps ) {
			$role = \get_role( $role_slug );
			if ( null === $role ) {
				continue;
			}

			foreach ( $caps as $cap ) {
				if ( true !== ( $role->capabilities[ $cap ] ?? false ) ) {
					$role->add_cap( $cap );
				}
			}
		}
	}

	/**
	 * Brings the granted capabilities to the desired map: grants the desired set, then revokes any
	 * capability the prior version granted but the desired map no longer lists (including one that
	 * moved to a different role, which the desired grant has already re-added there).
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<string, list<string>> $desired            Capabilities that should be present now, keyed by role slug.
	 * @param   array<string, list<string>> $previously_granted Capabilities the prior version granted, keyed by role slug.
	 */
	public function reconcile( array $desired, array $previously_granted ): void {
		$this->grant( $desired );
		$this->revoke_all( $this->difference( $previously_granted, $desired ) );
	}

	/**
	 * Removes each capability in the map from its role, skipping one not stored. Reads the role's stored
	 * capabilities rather than the filterable has_cap(), so a stored capability a filter masks is still
	 * removed.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<string, list<string>> $role_caps Capabilities to remove, keyed by role slug.
	 */
	public function revoke_all( array $role_caps ): void {
		foreach ( $role_caps as $role_slug => $caps ) {
			$role = \get_role( $role_slug );
			if ( null === $role ) {
				continue;
			}

			foreach ( $caps as $cap ) {
				if ( \array_key_exists( $cap, $role->capabilities ) ) {
					$role->remove_cap( $cap );
				}
			}
		}
	}

	// endregion

	// region HELPERS

	/**
	 * Returns, per role, the capabilities present in the first map but not the second, dropping a role
	 * whose difference is empty.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<string, list<string>> $from    Map to subtract from.
	 * @param   array<string, list<string>> $against Map to subtract.
	 *
	 * @return  array<string, list<string>>
	 */
	protected function difference( array $from, array $against ): array {
		$difference = array();
		foreach ( $from as $role_slug => $caps ) {
			$remaining = \array_values( \array_diff( $caps, $against[ $role_slug ] ?? array() ) );
			if ( array() !== $remaining ) {
				$difference[ $role_slug ] = $remaining;
			}
		}

		return $difference;
	}

	// endregion
}
