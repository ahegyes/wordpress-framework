<?php declare( strict_types=1 );

namespace Acme\ConsumerA;

\defined( 'ABSPATH' ) || exit;

/**
 * Keeps order exports locked unless the booted plugin relaxes the lock, so an incomplete build never opens them.
 *
 * The entry requires it by path before the floor gate, so it parses on PHP 7.4 and calls only what the oldest WordPress the bootstrap package runs on provides.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final class Safeguards {
	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	protected function __construct() {}

	// endregion

	// region METHODS

	/**
	 * Hooks the safeguards, as static closures, so that only their own filters relax them.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public static function register(): void {
		add_filter(
			'map_meta_cap',
			static function ( array $caps, string $cap ): array {
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
				return self::is_relaxed( apply_filters( 'acme_consumer_a/exports/should_restrict', true ) ) ? array( 'manage_woocommerce' ) : array( 'do_not_allow' );
			},
			10,
			2
		);
	}

	// endregion

	// region HELPERS

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
	protected static function is_relaxed( $filtered ): bool {
		return 0 < did_action( 'acme_consumer_a/boot/succeeded' ) && false === $filtered;
	}

	// endregion
}
