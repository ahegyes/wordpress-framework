<?php declare( strict_types=1 );

namespace Acme\PersonaSettings;

use Acme\PersonaSettings\Scoped\DeepWebSolutions\Framework\Settings\AdminPage\OptionsPageDefinitionInterface;

\defined( 'ABSPATH' ) || exit;

/**
 * Defines the plugin's settings page under Settings, whose one tab stores a limit of at least 1 in the plugin's settings option.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final class Settings implements OptionsPageDefinitionInterface {
	// region FIELDS AND CONSTANTS

	/**
	 * The prefix of the plugin's admin page and option.
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
					'slug'   => 'general',
					'title'  => __( 'General', 'acme-persona-settings' ),
					'option' => self::PREFIX . '_settings',
					'fields' => array(
						array(
							'type'     => 'number',
							'id'       => 'limit',
							'title'    => __( 'Limit', 'acme-persona-settings' ),
							'validate' => array( $this, 'validate_limit' ),
						),
					),
				),
			),
		);
	}

	// endregion

	// region METHODS

	/**
	 * Returns the submitted limit as an integer, or an error when it is not a whole number of at least 1.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   mixed $raw The submitted value.
	 *
	 * @return  int|\WP_Error
	 */
	public function validate_limit( mixed $raw ): int|\WP_Error {
		$limit = \filter_var( $raw, \FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );

		return false === $limit ? new \WP_Error( 'acme_ps_limit', __( 'Enter a whole number of at least 1.', 'acme-persona-settings' ) ) : $limit;
	}

	// endregion
}
