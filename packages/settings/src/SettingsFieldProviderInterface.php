<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings;

use DeepWebSolutions\Framework\Settings\ValueObjects\SettingsField;

/**
 * Contributes settings fields to a page or section from a component.
 *
 * The cross-component contribution seam: a component adds its own fields to a
 * settings screen it does not own. Providers are collected and merged by
 * {@see SettingsFieldAggregator}; one class may be both a runnable component and
 * a provider.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
interface SettingsFieldProviderInterface {
	/**
	 * Returns the fields this provider contributes.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  list<SettingsField>
	 */
	public function get_fields(): array;
}
