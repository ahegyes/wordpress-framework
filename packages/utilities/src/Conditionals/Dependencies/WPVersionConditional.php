<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Conditionals\Dependencies;

use DeepWebSolutions\Framework\Core\Conditional\ConditionalInterface;
use DeepWebSolutions\Framework\Shared\Version\Version;

/**
 * Pre-resolution gate that passes iff the current WordPress version is >= the minimum.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class WPVersionConditional implements ConditionalInterface {
	// region MAGIC METHODS

	/**
	 * Constructs the conditional with the minimum WordPress version required.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   Version $minimum Minimum WordPress version that satisfies the gate.
	 */
	public function __construct(
		protected readonly Version $minimum,
	) {}

	// endregion

	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function is_met(): bool {
		global $wp_version;

		if ( ! \is_string( $wp_version ) ) {
			return false;
		}

		// Mirror is_wp_version_compatible(), keeping is_met() total: strip the current version's
		// pre-release suffix (so "6.8-RC1" reads as "6.8"), and drop a trailing ".0" from a 3-part
		// minimum (so a 2-part current like "7.0" still satisfies a "7.0.0" minimum). Comparing the
		// raw runtime string rather than a parsed Version also avoids throwing on an odd $wp_version.
		$current = \explode( '-', $wp_version )[0];
		$minimum = $this->minimum->value;
		if ( \substr_count( $minimum, '.' ) > 1 && \str_ends_with( $minimum, '.0' ) ) {
			$minimum = \substr( $minimum, 0, -2 );
		}

		return \version_compare( $current, $minimum, '>=' );
	}

	// endregion
}
