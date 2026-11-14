<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\AdminPage;

use DeepWebSolutions\Framework\Settings\Forms;

\defined( 'ABSPATH' ) || exit;

/**
 * Defines an options page, which asks for its definition once, on first use after init, so that its labels are translated in the viewer's locale, and for a tab's fields only when that tab shows or saves.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @phpstan-import-type Page from OptionsPage
 * @phpstan-import-type Field from Forms
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

	/**
	 * Returns the fields of one of the page's tabs, each stored in the option its id names, which must not throw.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $page_id  The page's menu slug.
	 * @param   string $tab_slug The tab's slug.
	 *
	 * @return  list<Field>
	 */
	public function get_tab_fields( string $page_id, string $tab_slug ): array;
}
