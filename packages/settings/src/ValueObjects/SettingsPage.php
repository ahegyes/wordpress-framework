<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\ValueObjects;

/**
 * Declarative description of a settings page: a titled, capability-gated screen
 * composed of sections.
 *
 * The location is interpreted by the backend the page is registered with — a
 * WordPress parent slug for the options backend, a tab id for the WooCommerce
 * backend — so one descriptor serves either without change.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final readonly class SettingsPage {
	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string                $slug Menu slug, unique across registered pages.
	 * @param   string                $page_title Title rendered at the top of the page.
	 * @param   string                $menu_title Label shown in the admin menu.
	 * @param   string                $capability Capability required to view and save the page.
	 * @param   ?string               $location Backend-interpreted placement (WordPress parent slug, WooCommerce tab id); null uses the backend default.
	 * @param   list<SettingsSection> $sections Sections composing the page, in display order.
	 */
	public function __construct(
		public string $slug,
		public string $page_title,
		public string $menu_title,
		public string $capability,
		public ?string $location = null,
		public array $sections = array(),
	) {}

	// endregion
}
