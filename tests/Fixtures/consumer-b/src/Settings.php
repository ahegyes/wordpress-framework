<?php declare( strict_types=1 );

namespace Acme\ConsumerB;

use Acme\ConsumerB\Scoped\DeepWebSolutions\Framework\Core\ComponentInterface;
use Acme\ConsumerB\Scoped\DeepWebSolutions\Framework\Settings\AdminPage\OptionsPage;
use Acme\ConsumerB\Scoped\DeepWebSolutions\Framework\Settings\AdminPage\OptionsPageDefinitionInterface;

\defined( 'ABSPATH' ) || exit;

/**
 * Adds the plugin's settings page under Settings, whose one tab stores its fields in the plugin's settings option.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final class Settings implements ComponentInterface, OptionsPageDefinitionInterface {
	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	#[\Override]
	public function register_hooks(): void {
		new OptionsPage( Plugin::PREFIX, $this )->register_hooks();
	}

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
			'page_title' => __( 'Acme Consumer B', 'acme-consumer-b' ),
			'menu_title' => __( 'Acme Consumer B', 'acme-consumer-b' ),
			'tabs'       => array(
				array(
					'slug'   => 'general',
					'title'  => __( 'General', 'acme-consumer-b' ),
					'option' => Plugin::PREFIX . '_settings',
					'fields' => array(
						array(
							'type'  => 'text',
							'id'    => 'greeting',
							'title' => __( 'Greeting', 'acme-consumer-b' ),
						),
					),
				),
			),
		);
	}

	// endregion
}
