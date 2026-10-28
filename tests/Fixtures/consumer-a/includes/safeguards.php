<?php declare( strict_types=1 );
/**
 * Keeps order exports locked unless the booted plugin relaxes the lock, so an incomplete build never opens them.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @package Acme\ConsumerA
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'map_meta_cap', 'acme_ca_map_export_capability', 10, 2 );

/**
 * Returns whether a safeguard's filtered value relaxes it, which takes an explicit false once the plugin has finished booting.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param   mixed $filtered The value the safeguard's filter returned for true.
 *
 * @return  bool
 */
function acme_ca_is_relaxed( $filtered ): bool {
	return 0 < did_action( 'acme_ca_initialized' ) && false === $filtered;
}

/**
 * Maps the order export capability to do_not_allow, or to managing WooCommerce while the plugin relaxes the export lock.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param   string[] $caps The primitive capabilities the capability maps to.
 * @param   string   $cap  The capability being checked.
 *
 * @return  string[]
 */
function acme_ca_map_export_capability( array $caps, string $cap ): array {
	if ( 'acme_ca_export_orders' !== $cap ) {
		return $caps;
	}

	/**
	 * Filters whether order exports stay locked; an explicit false unlocks them once the plugin has booted.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   bool $restricted Whether order exports stay locked.
	 */
	return acme_ca_is_relaxed( apply_filters( 'acme_ca_restrict_exports', true ) ) ? array( 'manage_woocommerce' ) : array( 'do_not_allow' );
}
