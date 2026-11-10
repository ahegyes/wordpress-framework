<?php declare( strict_types=1 );

namespace Acme\PersonaSettings;

use Acme\PersonaSettings\Scoped\DeepWebSolutions\Framework\Settings\EditScreen\ObjectFormDefinitionInterface;

\defined( 'ABSPATH' ) || exit;

/**
 * Defines the plugin's form on the post edit screen, which stores a subtitle on each post.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final class Details implements ObjectFormDefinitionInterface {
	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	#[\Override]
	public function get_object_form( string $form_id, int $object_id ): array {
		return array(
			'title'      => __( 'Details', 'acme-persona-settings' ),
			'capability' => 'edit_posts',
			'fields'     => array(
				array(
					'type'  => 'text',
					'id'    => '_' . Settings::PREFIX . '_subtitle',
					'title' => __( 'Subtitle', 'acme-persona-settings' ),
				),
			),
		);
	}

	// endregion
}
