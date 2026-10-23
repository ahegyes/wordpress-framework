<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\AdminPage;

\defined( 'ABSPATH' ) || exit;

/**
 * Defines an options page, which asks for its definition once, on first use after init, so that its labels are translated in the viewer's locale.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @phpstan-import-type Page from OptionsPage
 */
interface OptionsPageDefinitionInterface {
	/**
	 * Returns the definition of the options page, which must not throw.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $page_id The page's menu slug.
	 *
	 * @return  Page
	 */
	public function get_options_page( string $page_id ): array;
}
