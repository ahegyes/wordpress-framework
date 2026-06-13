<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Conditionals\Dependencies;

use DeepWebSolutions\Framework\Core\Conditional\ConditionalInterface;

/**
 * Pre-resolution gate that passes iff a size-valued PHP ini directive provides at least the
 * given minimum. Intended for byte-shorthand directives such as `memory_limit`,
 * `upload_max_filesize`, and `post_max_size`; a directive set to `-1` (PHP's "unlimited")
 * satisfies any minimum.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class PHPIniSizeConditional implements ConditionalInterface {
	// region MAGIC METHODS

	/**
	 * Constructs the conditional with the ini directive name and minimum size.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $setting PHP ini directive name (e.g., `memory_limit`).
	 * @param   string $minimum Minimum size as byte shorthand (e.g., `128M`, `1G`).
	 */
	public function __construct(
		private readonly string $setting,
		private readonly string $minimum,
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
		$current = \ini_get( $this->setting );
		if ( false === $current ) {
			return false;
		}

		// PHP encodes "no limit" as -1, which must satisfy any finite minimum.
		if ( '-1' === \trim( $current ) ) {
			return true;
		}

		return \wp_convert_hr_to_bytes( $current ) >= \wp_convert_hr_to_bytes( $this->minimum );
	}

	// endregion
}
