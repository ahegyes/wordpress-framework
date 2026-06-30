<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\MetaField;

use DeepWebSolutions\Framework\Settings\MetaField\ValueObjects\FieldGroup;
use DeepWebSolutions\Framework\Settings\MetaField\ValueObjects\MetaBoxPlacement;
use DeepWebSolutions\Framework\Settings\Schema\Field\FieldProcessor;
use DeepWebSolutions\Framework\Settings\Schema\Field\FieldRenderer;

use function DeepWebSolutions\Framework\Settings\Schema\wordpress_field_type_sanitizers;

/**
 * Registers a field group as a post meta box.
 *
 * Adds a meta box on the post-type screen named by the placement and saves it on that type's save_post
 * hook, reading and writing post meta through a metadata repository. The placement's screen is the post
 * type. The current user must hold the placement's capability — by default the post's own edit_post meta
 * capability — for the object; the per-field gate and the nonce are the shared form engine's responsibility.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class PostMetaFieldStore {
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
	 * Registers the group's meta box and save hook on the placement's post-type screen.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   FieldGroup       $group     Group to register.
	 * @param   MetaBoxPlacement $placement Meta-box placement; its screen is the post type.
	 */
	public function register( FieldGroup $group, MetaBoxPlacement $placement ): void {
		$form = new ObjectFieldForm( new MetadataRepository( MetaType::Post ), $this->renderer, $this->processor );

		\add_action( "add_meta_boxes_{$placement->screen}", fn ( \WP_Post $post ) => $this->add_box( $group, $placement, $form, $post ) );
		\add_action( "save_post_{$placement->screen}", fn ( int $post_id ) => $this->save_box( $group, $placement, $form, $post_id ) );
	}

	// endregion

	// region HELPERS

	/**
	 * Registers the box with WordPress. Hooked to add_meta_boxes_{screen}.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   FieldGroup       $group     Group the box renders.
	 * @param   MetaBoxPlacement $placement Placement describing the box's screen, context, and priority.
	 * @param   ObjectFieldForm  $form      Engine that renders the group's fields.
	 * @param   \WP_Post         $post      Post being edited, whose capability gates the box.
	 */
	protected function add_box( FieldGroup $group, MetaBoxPlacement $placement, ObjectFieldForm $form, \WP_Post $post ): void {
		// Gate the box on the same capability as the save, so a user who reaches the edit screen but lacks
		// the box's capability for this post is neither shown the controls nor disclosed the stored values.
		if ( ! \current_user_can( $placement->capability ?? 'edit_post', $post->ID ) ) {
			return;
		}

		$priority = match ( $placement->priority ) {
			'core', 'high', 'low' => $placement->priority,
			default               => 'default',
		};

		\add_meta_box(
			$group->id,
			\esc_html( $group->title ),
			fn ( \WP_Post $screen_post ) => $form->render( $group, $screen_post->ID ),
			$placement->screen,
			$placement->context,
			$priority,
		);
	}

	/**
	 * Saves the box when the current user holds its capability for the post. Hooked to save_post_{screen}.
	 *
	 * An absent nonce (an autosave or revision carries none) is rejected by the form engine, so no autosave
	 * guard is needed here.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   FieldGroup       $group     Group to save.
	 * @param   MetaBoxPlacement $placement Placement whose capability gates the save.
	 * @param   ObjectFieldForm  $form      Engine that processes and persists the group's fields.
	 * @param   int              $post_id   Post whose meta to write.
	 */
	protected function save_box( FieldGroup $group, MetaBoxPlacement $placement, ObjectFieldForm $form, int $post_id ): void {
		$capability = $placement->capability ?? 'edit_post';
		if ( ! \current_user_can( $capability, $post_id ) ) {
			return;
		}

		$form->save( $group, $post_id );
	}

	// endregion
}
