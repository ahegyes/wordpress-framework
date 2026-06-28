<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\MetaField;

use DeepWebSolutions\Framework\Settings\MetaField\ValueObjects\FieldGroup;
use DeepWebSolutions\Framework\Settings\MetaField\ValueObjects\UserProfileFieldGroup;
use DeepWebSolutions\Framework\Settings\Schema\FieldProcessor;
use DeepWebSolutions\Framework\Settings\Schema\FieldRenderer;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsField;

/**
 * Registers a field group on the WordPress user-profile surface.
 *
 * Renders the group on the profile edit screens — always when an administrator edits another user
 * (edit_user_profile), and on a user's own profile (show_user_profile) unless the descriptor restricts
 * it — and saves on the matching update hooks, reading and writing user meta through a metadata
 * repository. The current user must be able to edit the target user; the per-field gate and the nonce
 * are the shared form engine's responsibility.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class UserProfileFieldStore {
	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   FieldRenderer  $renderer  Renderer for the field controls.
	 * @param   FieldProcessor $processor Processor for sanitizing submitted values.
	 */
	public function __construct(
		protected FieldRenderer $renderer = new FieldRenderer(),
		protected FieldProcessor $processor = new FieldProcessor(),
	) {}

	// endregion

	// region METHODS

	/**
	 * Registers the group's render and save hooks on the user-profile surface.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   UserProfileFieldGroup $profile Profile field group to register.
	 */
	public function register( UserProfileFieldGroup $profile ): void {
		$group = $profile->group;
		$form  = new ObjectFieldForm( new MetadataRepository( MetaType::User ), $this->renderer, $this->processor );

		\add_action( 'edit_user_profile', fn ( \WP_User $user ) => $this->render_profile( $group, $form, $user ) );
		\add_action( 'edit_user_profile_update', fn ( int $user_id ) => $this->save_profile( $group, $form, $user_id ) );

		if ( $profile->on_own_profile ) {
			\add_action( 'show_user_profile', fn ( \WP_User $user ) => $this->render_profile( $group, $form, $user ) );
			\add_action( 'personal_options_update', fn ( int $user_id ) => $this->save_profile( $group, $form, $user_id ) );
		}
	}

	// endregion

	// region HELPERS

	/**
	 * Renders the group inside a titled form table when the current user can edit the target user.
	 * Hooked to show_user_profile and edit_user_profile.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   FieldGroup      $group Group to render.
	 * @param   ObjectFieldForm $form  Engine that renders the group's fields.
	 * @param   \WP_User        $user  User whose profile is being edited.
	 */
	protected function render_profile( FieldGroup $group, ObjectFieldForm $form, \WP_User $user ): void {
		if ( ! \current_user_can( 'edit_user', $user->ID ) ) {
			return;
		}

		echo '<h2>' . \esc_html( $group->title ) . '</h2><table class="form-table" role="presentation">';
		$form->render( $group, $user->ID, $this->row() );
		echo '</table>';
	}

	/**
	 * Saves the group when the current user can edit the target user.
	 * Hooked to personal_options_update and edit_user_profile_update.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   FieldGroup      $group   Group to save.
	 * @param   ObjectFieldForm $form    Engine that processes and persists the group's fields.
	 * @param   int             $user_id User whose profile was submitted.
	 */
	protected function save_profile( FieldGroup $group, ObjectFieldForm $form, int $user_id ): void {
		if ( ! \current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		$form->save( $group, $user_id );
	}

	/**
	 * The row closure wrapping each control in a form-table row.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  \Closure
	 */
	protected function row(): \Closure {
		return static fn ( SettingsField $field, string $control ): string =>
			'<tr><th scope="row">' . \esc_html( $field->label ) . '</th><td>' . $control . '</td></tr>';
	}

	// endregion
}
