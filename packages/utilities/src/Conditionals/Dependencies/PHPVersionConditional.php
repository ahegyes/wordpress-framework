<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Conditionals\Dependencies;

use DeepWebSolutions\Framework\Core\Conditional\ConditionalInterface;
use DeepWebSolutions\Framework\Shared\Version\Version;

/**
 * Pre-resolution gate that passes iff the current PHP version is >= the minimum.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class PHPVersionConditional implements ConditionalInterface {
	// region MAGIC METHODS

	/**
	 * Constructs the conditional with the minimum PHP version required.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   Version $minimum Minimum PHP version that satisfies the gate.
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
	#[\Override]
	public function is_met(): bool {
		// Compare the raw runtime string rather than parsing it into a Version: PHP encodes
		// pre-releases without a dash (e.g. "8.5.0RC1"), which the Version grammar rejects, and
		// is_met() must stay total (mirrors the total is_php_version_compatible()).
		return \version_compare( \PHP_VERSION, $this->minimum->value, '>=' );
	}

	// endregion
}
