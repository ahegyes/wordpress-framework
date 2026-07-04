<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\MetaField;

use DeepWebSolutions\Framework\Settings\MetaField\ValueObjects\FieldGroup;
use DeepWebSolutions\Framework\Settings\MetaField\ValueObjects\TermFieldGroup;
use DeepWebSolutions\Framework\Settings\Schema\Field\FieldProcessor;
use DeepWebSolutions\Framework\Settings\Schema\Field\FieldRenderer;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsField;

use function DeepWebSolutions\Framework\Settings\Schema\wordpress_field_type_sanitizers;

/**
 * Registers a field group on a taxonomy's term add/edit surfaces.
 *
 * Renders the group's fields into the add-new-term and term-edit screens for the descriptor's taxonomy and
 * saves them when the term is created or updated, reading and writing term meta through a metadata repository.
 * The current user must be able to edit the taxonomy's terms or edit the term; the per-field gate and the nonce
 * are the shared form engine's responsibility. The edit screen supplies the surrounding form table, so edit
 * fields render as rows; the add screen uses WordPress' div.form-field markup.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class TermFieldStore {
	// region FIELDS AND CONSTANTS

	/**
	 * Registered groups and forms keyed by taxonomy and group id.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     array<string, array<string, array{group: FieldGroup, form: ObjectFieldForm}>>
	 */
	protected array $registrations = array();

	// endregion

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
	 * Registers the group's render and save hooks on the taxonomy's term add/edit surfaces.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   TermFieldGroup $term_group Term field group to register.
	 */
	public function register( TermFieldGroup $term_group ): void {
		$group = $term_group->group;
		$form  = new ObjectFieldForm( new MetadataRepository( MetaType::Term ), $this->renderer, $this->processor );

		$this->registrations[ $term_group->taxonomy ][ $group->id ] = array(
			'group' => $group,
			'form'  => $form,
		);

		\add_action( "{$term_group->taxonomy}_add_form_fields", array( $this, 'render_add_term' ) );
		\add_action( "{$term_group->taxonomy}_edit_form_fields", array( $this, 'render_edit_term' ) );
		\add_action( "created_{$term_group->taxonomy}", array( $this, 'save_created_term' ) );
		\add_action( "edited_{$term_group->taxonomy}", array( $this, 'save_edited_term' ) );
	}

	// endregion

	// region HOOKS

	/**
	 * Renders registered groups as add-new-term form fields when the current user can edit the
	 * taxonomy's terms — the capability WordPress itself gates the add-term form on.
	 * Hooked to {taxonomy}_add_form_fields.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $taxonomy Taxonomy whose add form is rendering.
	 */
	public function render_add_term( string $taxonomy ): void {
		if ( ! $this->can_edit_terms( $taxonomy ) ) {
			return;
		}

		foreach ( $this->registrations_for( $taxonomy ) as $registration ) {
			$registration['form']->render( $registration['group'], 0, $this->add_row() );
		}
	}

	/**
	 * Renders registered groups as term-edit form rows when the current user can edit the term.
	 * Hooked to {taxonomy}_edit_form_fields.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   \WP_Term $term Term being edited.
	 */
	public function render_edit_term( \WP_Term $term ): void {
		if ( ! \current_user_can( 'edit_term', $term->term_id ) ) {
			return;
		}

		foreach ( $this->registrations_for( $term->taxonomy ) as $registration ) {
			$registration['form']->render( $registration['group'], $term->term_id, $this->edit_row() );
		}
	}

	/**
	 * Saves registered groups for a newly created term when the current user can edit it.
	 * Hooked to created_{taxonomy}.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $term_id Term whose meta to write.
	 */
	public function save_created_term( int $term_id ): void {
		$this->save_term( $term_id, 0 );
	}

	/**
	 * Saves registered groups for an updated term when the current user can edit it.
	 * Hooked to edited_{taxonomy}.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $term_id Term whose meta to write.
	 */
	public function save_edited_term( int $term_id ): void {
		$this->save_term( $term_id );
	}

	// endregion

	// region HELPERS

	/**
	 * Saves registered groups for a term, using a separate nonce object id on create.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int  $term_id         Term whose meta to write.
	 * @param   ?int $nonce_object_id Object id the nonce is bound to; null uses $term_id.
	 */
	protected function save_term( int $term_id, ?int $nonce_object_id = null ): void {
		if ( ! \current_user_can( 'edit_term', $term_id ) ) {
			return;
		}

		$term = \get_term( $term_id );
		if ( ! $term instanceof \WP_Term ) {
			return;
		}

		foreach ( $this->registrations_for( $term->taxonomy ) as $registration ) {
			$registration['form']->save( $registration['group'], $term_id, $nonce_object_id );
		}
	}

	/**
	 * Registered groups and forms for a taxonomy.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $taxonomy Taxonomy to resolve.
	 *
	 * @return  array<string, array{group: FieldGroup, form: ObjectFieldForm}>
	 */
	protected function registrations_for( string $taxonomy ): array {
		return $this->registrations[ $taxonomy ] ?? array();
	}

	/**
	 * Whether the current user can edit a taxonomy's terms.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $taxonomy Taxonomy to check.
	 *
	 * @return  bool
	 */
	protected function can_edit_terms( string $taxonomy ): bool {
		$taxonomy_object = \get_taxonomy( $taxonomy );
		if ( false === $taxonomy_object ) {
			return false;
		}

		return \current_user_can( $taxonomy_object->cap->edit_terms );
	}

	/**
	 * The row closure wrapping each control in a term-add form field.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  \Closure
	 */
	protected function add_row(): \Closure {
		return static fn ( SettingsField $field, string $control ): string =>
			'<div class="form-field term-' . \esc_attr( $field->id ) . '-wrap">'
			. '<label>' . \esc_html( $field->label ) . '</label>' . $control . '</div>';
	}

	/**
	 * The row closure wrapping each control in a term-edit form row.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  \Closure
	 */
	protected function edit_row(): \Closure {
		return static fn ( SettingsField $field, string $control ): string =>
			'<tr class="form-field"><th scope="row">' . \esc_html( $field->label ) . '</th><td>' . $control . '</td></tr>';
	}

	// endregion
}
