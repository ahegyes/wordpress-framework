<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Schema\ValueObjects;

use DeepWebSolutions\Framework\Settings\Schema\Exceptions\InvalidSettingsPageException;

use function DeepWebSolutions\Framework\Shared\Identifier\is_valid_identifier;

/**
 * Descriptor for a settings page: a titled, capability-gated screen composed
 * of sections.
 *
 * The location is interpreted by the backend the page is registered with — an
 * admin parent-menu slug for a menu-based backend, a tab id for a tab-based
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
	 * @param   string                $slug Menu slug, unique across registered pages; a lowercase token matching the shared identifier charset — the container descriptor's `slug` counterpart to a member's `id`.
	 * @param   string                $page_title Title rendered at the top of the page.
	 * @param   string                $menu_title Label shown in the admin menu.
	 * @param   string                $capability Capability required to view and save the page.
	 * @param   list<SettingsSection> $sections Sections composing the page, in display order.
	 * @param   ?string               $location Backend-interpreted placement (an admin parent-menu slug, or a settings-tab id); null uses the backend default.
	 *
	 * @throws  InvalidSettingsPageException If $slug does not match the slug charset.
	 */
	public function __construct(
		public string $slug,
		public string $page_title,
		public string $menu_title,
		public string $capability,
		public array $sections,
		public ?string $location = null,
	) {
		if ( ! is_valid_identifier( $slug ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- framework-internal exception; never reaches an HTML output context unescaped.
			throw new InvalidSettingsPageException( "Invalid settings page slug: '$slug'." );
		}
	}

	// endregion
}
