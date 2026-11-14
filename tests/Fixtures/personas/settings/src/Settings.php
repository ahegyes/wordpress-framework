<?php declare( strict_types=1 );

namespace Acme\PersonaSettings;

use Acme\PersonaSettings\Scoped\DeepWebSolutions\Framework\Settings\AdminPage\OptionsPageDefinitionInterface;

\defined( 'ABSPATH' ) || exit;

/**
 * Defines the plugin's settings page under Settings, whose one tab stores a whole-number limit of at least 1 in an option of its own.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final class Settings implements OptionsPageDefinitionInterface {
	// region FIELDS AND CONSTANTS

	/**
	 * The plugin's slug, which is also its text domain and, in snake case, the root of its hook names.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     string
	 */
	public const string SLUG = 'acme-persona-settings';

	/**
	 * The prefix of the plugin's admin page and of the names it stores its data under.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     string
	 */
	public const string PREFIX = 'acme_ps';

	// endregion

	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	#[\Override]
	public function get_options_page( string $page_id ): array {
		return array(
			'parent'     => 'options-general.php',
			'capability' => 'manage_options',
			'page_title' => __( 'Acme Persona Settings', 'acme-persona-settings' ),
			'menu_title' => __( 'Acme Persona Settings', 'acme-persona-settings' ),
			'tabs'       => array(
				array(
					'slug'  => 'general',
					'title' => __( 'General', 'acme-persona-settings' ),
				),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	#[\Override]
	public function get_tab_fields( string $page_id, string $tab_slug ): array {
		return match ( $tab_slug ) {
			'general' => array(
				array(
					'type'              => 'number',
					'id'                => 'acme_ps_limit',
					'title'             => __( 'Limit', 'acme-persona-settings' ),
					'default'           => '10',
					'custom_attributes' => array(
						'min'  => '1',
						'step' => '1',
					),
				),
			),
			default   => array(),
		};
	}

	// endregion
}
