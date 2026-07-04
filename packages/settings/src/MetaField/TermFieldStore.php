<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\MetaField;

use DeepWebSolutions\Framework\Settings\MetaField\ValueObjects\FieldGroup;
use DeepWebSolutions\Framework\Settings\MetaField\ValueObjects\TermFieldGroup;
use DeepWebSolutions\Framework\Settings\Schema\Exceptions\DuplicateSettingsFieldException;
use DeepWebSolutions\Framework\Settings\Schema\Exceptions\InvalidSettingsFieldException;
use DeepWebSolutions\Framework\Settings\Schema\Field\FieldProcessor;
use DeepWebSolutions\Framework\Settings\Schema\Field\FieldRenderer;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsField;

/**
 * Registers a field group on a taxonomy's term add/edit surfaces and stores its fields as term meta.
 *
 * Renders the group's fields into the add-new-term and term-edit screens for the descriptor's taxonomy and
 * saves them when the term is created or updated, reading and writing term meta through a metadata repository.
 * The current user must be able to edit the taxonomy's terms or edit the term; the per-field gate and the nonce
 * are the shared form engine's responsibility. The edit screen supplies the surrounding form table, so edit
 * fields render as rows; the add screen uses WordPress' div.form-field markup.
 *
 * Beyond registration, the store exposes field-addressed CRUD over the same storage keys and value
 * semantics the form path applies — get/set/has/delete by group and field id — plus meta_keys() for the
 * consumer's uninstall cleanup. Object fields are revoke-based, so reads never fall back to the field's
 * declared default.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class TermFieldStore {
	// region FIELDS AND CONSTANTS

	/**
	 * Registered groups keyed by taxonomy and group id.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     array<string, array<string, FieldGroup>>
	 */
	protected array $registrations = array();

	/**
	 * Shared form engine that renders and saves the registered groups and resolves their storage keys.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     ObjectFieldForm
	 */
	protected ObjectFieldForm $form;

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   FieldRenderer                 $renderer   Renderer for the field controls.
	 * @param   ?FieldProcessor               $processor  Processor for sanitizing submitted values; null applies one carrying the per-type default sanitizers.
	 * @param   ObjectMetaRepositoryInterface $repository Repository the groups' fields read from and write to.
	 */
	public function __construct(
		FieldRenderer $renderer = new FieldRenderer(),
		?FieldProcessor $processor = null,
		protected ObjectMetaRepositoryInterface $repository = new MetadataRepository( MetaType::Term ),
	) {
		$this->form = new ObjectFieldForm( $this->repository, $renderer, $processor );
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

		$this->registrations[ $term_group->taxonomy ][ $group->id ] = $group;

		\add_action( "{$term_group->taxonomy}_add_form_fields", array( $this, 'render_add_term' ) );
		\add_action( "{$term_group->taxonomy}_edit_form_fields", array( $this, 'render_edit_term' ) );
		\add_action( "created_{$term_group->taxonomy}", array( $this, 'save_created_term' ) );
		\add_action( "edited_{$term_group->taxonomy}", array( $this, 'save_edited_term' ) );
	}

	/**
	 * Retrieves a field's stored value for a term, or $default_value when nothing is stored. Object fields
	 * are revoke-based, so the field's declared default is never a read-time fallback.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   FieldGroup $group         Group that declares the field.
	 * @param   int        $term_id       Term to read.
	 * @param   string     $field_id      Field whose value to read.
	 * @param   mixed      $default_value Value to return when nothing is stored.
	 *
	 * @throws  DuplicateSettingsFieldException If two of the group's fields share an id or storage key.
	 * @throws  InvalidSettingsFieldException If the group declares no field with the given id.
	 *
	 * @return  mixed
	 */
	public function get( FieldGroup $group, int $term_id, string $field_id, mixed $default_value = null ): mixed {
		return $this->repository->get( $term_id, $this->form->meta_key_of( $group, $term_id, $field_id ), $default_value );
	}

	/**
	 * Persists a field's value for a term with the form path's store-or-revoke semantics: a checkbox
	 * value is stored in its canonical 'yes'/'no' form (false stores 'no'), and a non-checkbox value a
	 * form save would not store — false, a cleared field ('') or an empty multi-select (array()) —
	 * revokes the meta key instead. The write is programmatic: the descriptor's sanitize/validate seam
	 * applies to form submissions only.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   FieldGroup $group    Group that declares the field.
	 * @param   int        $term_id  Term to write.
	 * @param   string     $field_id Field whose value to write.
	 * @param   mixed      $value    Value to persist.
	 *
	 * @throws  DuplicateSettingsFieldException If two of the group's fields share an id or storage key.
	 * @throws  InvalidSettingsFieldException If the group declares no field with the given id.
	 */
	public function set( FieldGroup $group, int $term_id, string $field_id, mixed $value ): void {
		$this->form->store( $group, $term_id, $field_id, $value );
	}

	/**
	 * Whether a real value is stored for a field on a term.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   FieldGroup $group    Group that declares the field.
	 * @param   int        $term_id  Term to check.
	 * @param   string     $field_id Field to check.
	 *
	 * @throws  DuplicateSettingsFieldException If two of the group's fields share an id or storage key.
	 * @throws  InvalidSettingsFieldException If the group declares no field with the given id.
	 *
	 * @return  bool
	 */
	public function has( FieldGroup $group, int $term_id, string $field_id ): bool {
		return $this->repository->has( $term_id, $this->form->meta_key_of( $group, $term_id, $field_id ) );
	}

	/**
	 * Deletes a field's stored value from a term.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   FieldGroup $group    Group that declares the field.
	 * @param   int        $term_id  Term to clear.
	 * @param   string     $field_id Field to clear.
	 *
	 * @throws  DuplicateSettingsFieldException If two of the group's fields share an id or storage key.
	 * @throws  InvalidSettingsFieldException If the group declares no field with the given id.
	 *
	 * @return  bool True if a value was deleted, false if none existed.
	 */
	public function delete( FieldGroup $group, int $term_id, string $field_id ): bool {
		return $this->repository->delete( $term_id, $this->form->meta_key_of( $group, $term_id, $field_id ) );
	}

	/**
	 * Returns every storage key a group's fields resolve to, for the consumer's uninstall cleanup. The
	 * fields are built through the group's provider for object id 0 — the objectless evaluation the add
	 * screen also uses — so a provider that varies its fields per object is enumerated by the consumer
	 * per object instead.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   FieldGroup $group Group whose storage keys to enumerate.
	 *
	 * @throws  DuplicateSettingsFieldException If two of the group's fields share an id or storage key.
	 *
	 * @return  list<string>
	 */
	public function meta_keys( FieldGroup $group ): array {
		return $this->form->meta_keys( $group );
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

		foreach ( $this->registrations_for( $taxonomy ) as $group ) {
			$this->form->render( $group, 0, $this->add_row() );
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

		foreach ( $this->registrations_for( $term->taxonomy ) as $group ) {
			$this->form->render( $group, $term->term_id, $this->edit_row() );
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

		foreach ( $this->registrations_for( $term->taxonomy ) as $group ) {
			$this->form->save( $group, $term_id, $nonce_object_id );
		}
	}

	/**
	 * Registered groups for a taxonomy.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $taxonomy Taxonomy to resolve.
	 *
	 * @return  array<string, FieldGroup>
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
