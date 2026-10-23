<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\EditScreen;

\defined( 'ABSPATH' ) || exit;

/**
 * Defines an object form for each object it edits, which asks for its definition once per object, only after a save's nonce is verified and never before init.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @phpstan-import-type Form from ObjectForm
 */
interface ObjectFormDefinitionInterface {
	/**
	 * Returns the definition of the form for the object, which must not throw.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $form_id   The form's id.
	 * @param   int    $object_id The id of the object being edited or saved.
	 *
	 * @return  Form
	 */
	public function get_object_form( string $form_id, int $object_id ): array;
}
