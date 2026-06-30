<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\MetaField;

use DeepWebSolutions\Framework\Settings\MetaField\ValueObjects\FieldGroup;
use DeepWebSolutions\Framework\Settings\MetaField\ValueObjects\TermFieldGroup;
use DeepWebSolutions\Framework\Settings\Schema\Field\FieldProcessor;
use DeepWebSolutions\Framework\Settings\Schema\Field\FieldRenderer;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsField;

use function DeepWebSolutions\Framework\Settings\Schema\wordpress_field_type_sanitizers;

/**
 * Registers a field group on a taxonomy's term-edit surface.
 *
 * Renders the group's fields into the term-edit screen for the descriptor's taxonomy and saves them when
 * the term is updated, reading and writing term meta through a metadata repository. The current user must
 * be able to edit the term; the per-field gate and the nonce are the shared form engine's responsibility.
 * The term-edit screen supplies the surrounding form table, so the fields render as plain rows.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class TermFieldStore {
	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   FieldRenderer   $renderer  Renderer for the field controls.
	 * @param   ?FieldProcessor $processor Processor for sanitizing submitted values; null applies one carrying the per-type default sanitizers.
	 */
	public function __construct(
		protected FieldRenderer $renderer = new FieldRenderer(),
		protected ?FieldProcessor $processor = null,
	) {
		$this->processor ??= new FieldProcessor( type_sanitizers: wordpress_field_type_sanitizers() );
	}

	// endregion

	// region METHODS

	/**
	 * Registers the group's render and save hooks on the taxonomy's term-edit surface.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   TermFieldGroup $term_group Term field group to register.
	 */
	public function register( TermFieldGroup $term_group ): void {
		$group = $term_group->group;
		$form  = new ObjectFieldForm( new MetadataRepository( MetaType::Term ), $this->renderer, $this->processor );

		\add_action( "{$term_group->taxonomy}_edit_form_fields", fn ( \WP_Term $term ) => $this->render_term( $group, $form, $term ) );
		\add_action( "edited_{$term_group->taxonomy}", fn ( int $term_id ) => $this->save_term( $group, $form, $term_id ) );
	}

	// endregion

	// region HELPERS

	/**
	 * Renders the group as term-edit form rows when the current user can edit the term.
	 * Hooked to {taxonomy}_edit_form_fields.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   FieldGroup      $group Group to render.
	 * @param   ObjectFieldForm $form  Engine that renders the group's fields.
	 * @param   \WP_Term        $term  Term being edited.
	 */
	protected function render_term( FieldGroup $group, ObjectFieldForm $form, \WP_Term $term ): void {
		if ( ! \current_user_can( 'edit_term', $term->term_id ) ) {
			return;
		}

		$form->render( $group, $term->term_id, $this->row() );
	}

	/**
	 * Saves the group when the current user can edit the term.
	 * Hooked to edited_{taxonomy}.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   FieldGroup      $group   Group to save.
	 * @param   ObjectFieldForm $form    Engine that processes and persists the group's fields.
	 * @param   int             $term_id Term whose meta to write.
	 */
	protected function save_term( FieldGroup $group, ObjectFieldForm $form, int $term_id ): void {
		if ( ! \current_user_can( 'edit_term', $term_id ) ) {
			return;
		}

		$form->save( $group, $term_id );
	}

	/**
	 * The row closure wrapping each control in a term-edit form row.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  \Closure
	 */
	protected function row(): \Closure {
		return static fn ( SettingsField $field, string $control ): string =>
			'<tr class="form-field"><th scope="row">' . \esc_html( $field->label ) . '</th><td>' . $control . '</td></tr>';
	}

	// endregion
}
