<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\MetaField\Surfaces;

use DeepWebSolutions\Framework\Settings\MetaField\ObjectFieldForm;
use DeepWebSolutions\Framework\Settings\MetaField\ValueObjects\FieldGroup;
use DeepWebSolutions\Framework\Settings\MetaField\ValueObjects\MetaBoxPlacement;
use DeepWebSolutions\Framework\Settings\Schema\Exceptions\DuplicateSettingsFieldException;
use DeepWebSolutions\Framework\Settings\Schema\Exceptions\InvalidSettingsFieldException;
use DeepWebSolutions\Framework\Settings\Schema\Field\FieldProcessor;
use DeepWebSolutions\Framework\Settings\Schema\Field\FieldRenderer;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsField;
use DeepWebSolutions\Framework\Storage\ObjectMeta\MetadataRepository;
use DeepWebSolutions\Framework\Storage\ObjectMeta\MetaType;
use DeepWebSolutions\Framework\Storage\ObjectMeta\ObjectMetaRepositoryInterface;

use function DeepWebSolutions\Framework\Settings\Schema\meta_box_row_html;

/**
 * Surface that mounts a field group onto the post edit screen as a meta box and stores its fields as post meta.
 *
 * Adds a meta box on the post-type screen named by the placement and saves it on that type's save_post
 * hook, reading and writing post meta through a metadata repository. The placement's screen is the post
 * type. The current user must hold the placement's capability — by default the post's own edit_post meta
 * capability — for the object; the per-field gate and the nonce are the shared form engine's responsibility.
 *
 * Beyond registration, the surface exposes field-addressed CRUD over the same storage keys and value
 * semantics the form path applies — get/set/has/delete by group and field id — plus meta_keys() for the
 * consumer's uninstall cleanup. Object fields are revoke-based, so reads never fall back to the field's
 * declared default.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class PostMetaFieldSurface {
	// region FIELDS AND CONSTANTS

	/**
	 * Registered groups and placements keyed by screen and group id.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     array<string, array<string, array{group: FieldGroup, placement: MetaBoxPlacement}>>
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
		protected ObjectMetaRepositoryInterface $repository = new MetadataRepository( MetaType::Post ),
	) {
		$this->form = new ObjectFieldForm( $this->repository, $renderer, $processor );
	}

	// endregion

	// region METHODS

	/**
	 * Registers the group's meta box and save hook on the placement's post-type screen.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   FieldGroup       $group     Group to register.
	 * @param   MetaBoxPlacement $placement Meta-box placement; its screen is the post type.
	 */
	public function register( FieldGroup $group, MetaBoxPlacement $placement ): void {
		$this->registrations[ $placement->screen ][ $group->id ] = array(
			'group'     => $group,
			'placement' => $placement,
		);

		\add_action( "add_meta_boxes_{$placement->screen}", array( $this, 'add_boxes' ) );
		\add_action( "save_post_{$placement->screen}", array( $this, 'save_boxes' ) );
	}

	/**
	 * Retrieves a field's stored value for a post — {@see ObjectFieldForm::get()} for the read semantics.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   FieldGroup $group         Group that declares the field.
	 * @param   int        $post_id       Post to read.
	 * @param   string     $field_id      Field whose value to read.
	 * @param   mixed      $default_value Value to return when nothing is stored.
	 *
	 * @throws  DuplicateSettingsFieldException If two of the group's fields share an id or storage key.
	 * @throws  InvalidSettingsFieldException If the group declares no field with the given id.
	 *
	 * @return  mixed
	 */
	public function get( FieldGroup $group, int $post_id, string $field_id, mixed $default_value = null ): mixed {
		return $this->form->get( $group, $post_id, $field_id, $default_value );
	}

	/**
	 * Persists a field's value for a post — {@see ObjectFieldForm::set()} for the store-or-revoke semantics.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   FieldGroup $group    Group that declares the field.
	 * @param   int        $post_id  Post to write.
	 * @param   string     $field_id Field whose value to write.
	 * @param   mixed      $value    Value to persist.
	 *
	 * @throws  DuplicateSettingsFieldException If two of the group's fields share an id or storage key.
	 * @throws  InvalidSettingsFieldException If the group declares no field with the given id.
	 */
	public function set( FieldGroup $group, int $post_id, string $field_id, mixed $value ): void {
		$this->form->set( $group, $post_id, $field_id, $value );
	}

	/**
	 * Whether a real value is stored for a field on a post — {@see ObjectFieldForm::has()}.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   FieldGroup $group    Group that declares the field.
	 * @param   int        $post_id  Post to check.
	 * @param   string     $field_id Field to check.
	 *
	 * @throws  DuplicateSettingsFieldException If two of the group's fields share an id or storage key.
	 * @throws  InvalidSettingsFieldException If the group declares no field with the given id.
	 *
	 * @return  bool
	 */
	public function has( FieldGroup $group, int $post_id, string $field_id ): bool {
		return $this->form->has( $group, $post_id, $field_id );
	}

	/**
	 * Deletes a field's stored value from a post — {@see ObjectFieldForm::delete()}.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   FieldGroup $group    Group that declares the field.
	 * @param   int        $post_id  Post to clear.
	 * @param   string     $field_id Field to clear.
	 *
	 * @throws  DuplicateSettingsFieldException If two of the group's fields share an id or storage key.
	 * @throws  InvalidSettingsFieldException If the group declares no field with the given id.
	 *
	 * @return  bool True if a value was deleted, false if none existed.
	 */
	public function delete( FieldGroup $group, int $post_id, string $field_id ): bool {
		return $this->form->delete( $group, $post_id, $field_id );
	}

	/**
	 * Returns every storage key a group's fields resolve to, for the consumer's uninstall cleanup. The
	 * fields are built through the group's provider for object id 0 — the objectless evaluation — so a
	 * provider that varies its fields per object is enumerated by the consumer per object instead.
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
	 * Adds the boxes registered for the post's type. Hooked to add_meta_boxes_{screen}.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   \WP_Post $post Post being edited.
	 */
	public function add_boxes( \WP_Post $post ): void {
		foreach ( $this->registrations_for( $post->post_type ) as $registration ) {
			$this->add_box( $registration['group'], $registration['placement'], $post );
		}
	}

	/**
	 * Saves the boxes registered for the post's type. Hooked to save_post_{screen}.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $post_id Post whose meta to write.
	 *
	 * @throws  DuplicateSettingsFieldException If two of a group's fields share an id or storage key.
	 */
	public function save_boxes( int $post_id ): void {
		$post_type = \get_post_type( $post_id );
		if ( false === $post_type ) {
			return;
		}

		foreach ( $this->registrations_for( $post_type ) as $registration ) {
			$this->save_box( $registration['group'], $registration['placement'], $post_id );
		}
	}

	// endregion

	// region HELPERS

	/**
	 * Registered groups and placements for a screen.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $screen Screen to resolve.
	 *
	 * @return  array<string, array{group: FieldGroup, placement: MetaBoxPlacement}>
	 */
	protected function registrations_for( string $screen ): array {
		return $this->registrations[ $screen ] ?? array();
	}

	/**
	 * Registers the box with WordPress.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   FieldGroup       $group     Group the box renders.
	 * @param   MetaBoxPlacement $placement Placement describing the box's screen, context, and priority.
	 * @param   \WP_Post         $post      Post being edited, whose capability gates the box.
	 */
	protected function add_box( FieldGroup $group, MetaBoxPlacement $placement, \WP_Post $post ): void {
		// Gate the box on the same capability as the save, so a user who reaches the edit screen but lacks
		// the box's capability for this post is neither shown the controls nor disclosed the stored values.
		if ( ! \current_user_can( $placement->get_capability(), $post->ID ) ) {
			return;
		}

		/** @var 'high'|'core'|'default'|'low' $priority */ // phpcs:ignore Generic.Commenting.DocComment.MissingShort -- inline @var type assertion; the placement constructor validates the closed set.
		$priority = $placement->priority;

		\add_meta_box(
			$group->id,
			\esc_html( $group->title ),
			fn ( \WP_Post $screen_post ) => $this->form->render( $group, $screen_post->ID, $this->box_row() ),
			$placement->screen,
			$placement->context,
			$priority,
		);
	}

	/**
	 * The row closure wrapping each control in a meta-box row — {@see meta_box_row_html()}.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  \Closure
	 */
	protected function box_row(): \Closure {
		return static fn ( SettingsField $field, string $control, string $control_id ): string => meta_box_row_html( $field, $control, $control_id );
	}

	/**
	 * Saves the box when the current user holds its capability for the post.
	 *
	 * An absent nonce (an autosave or revision carries none) is rejected by the form engine, so no autosave
	 * guard is needed here.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   FieldGroup       $group     Group to save.
	 * @param   MetaBoxPlacement $placement Placement whose capability gates the save.
	 * @param   int              $post_id   Post whose meta to write.
	 *
	 * @throws  DuplicateSettingsFieldException If two of the group's fields share an id or storage key.
	 */
	protected function save_box( FieldGroup $group, MetaBoxPlacement $placement, int $post_id ): void {
		if ( ! \current_user_can( $placement->get_capability(), $post_id ) ) {
			return;
		}

		$this->form->save( $group, $post_id );
	}

	// endregion
}
