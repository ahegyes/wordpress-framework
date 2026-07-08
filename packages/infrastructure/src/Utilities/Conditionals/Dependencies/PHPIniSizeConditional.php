<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Conditionals\Dependencies;

use DeepWebSolutions\Framework\Core\Conditional\ConditionalInterface;
use DeepWebSolutions\Framework\Utilities\Conditionals\Exceptions\InvalidConditionalConfigurationException;

/**
 * Pre-resolution gate that passes iff a size-valued PHP ini directive provides at least the
 * given minimum. Intended for byte-shorthand directives such as `memory_limit`,
 * `upload_max_filesize`, and `post_max_size`; a directive set to `-1` (PHP's "unlimited")
 * satisfies any minimum.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final readonly class PHPIniSizeConditional implements ConditionalInterface {
	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $setting PHP ini directive name (e.g., `memory_limit`).
	 * @param   string $minimum Minimum size as byte shorthand (e.g., `128M`, `1G`).
	 *
	 * @throws  InvalidConditionalConfigurationException When $setting is empty or $minimum is not integer byte shorthand.
	 */
	public function __construct(
		protected string $setting,
		protected string $minimum,
	) {
		if ( '' === \trim( $setting ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- framework-internal exception; never reaches an HTML output context unescaped.
			throw new InvalidConditionalConfigurationException( "Invalid ini directive name: '$setting'. Use a non-empty PHP ini directive name (e.g. 'memory_limit')." );
		}

		// The accepted grammar is the well-formed subset of what wp_convert_hr_to_bytes() parses
		// (leading digits with an optional single k/m/g multiplier), so is_met() compares exactly
		// the bytes the minimum spells out.
		if ( 1 !== \preg_match( '/^\d+[kmgKMG]?$/', \trim( $minimum ) ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- framework-internal exception; never reaches an HTML output context unescaped.
			throw new InvalidConditionalConfigurationException( "Invalid ini size minimum: '$minimum'. Use integer byte shorthand — digits with an optional k/m/g suffix (e.g. '128M', '1g')." );
		}
	}

	// endregion

	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
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
