<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings;

/**
 * Supplies the value-to-label option set for a choice-typed settings field.
 *
 * Lets a field defer its options to a runtime source — post types, user roles,
 * payment gateways — not known when the descriptor is declared. The same resolved
 * set drives both rendering and validation, so a value is never accepted that the
 * field would not have offered.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
interface SettingsOptionsProviderInterface {
	/**
	 * Returns the available options as a value-to-label map.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  array<array-key, mixed>
	 */
	public function get_options(): array;
}
