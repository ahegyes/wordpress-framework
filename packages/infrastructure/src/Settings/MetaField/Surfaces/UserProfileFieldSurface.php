<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\MetaField\Surfaces;

use DeepWebSolutions\Framework\Settings\MetaField\ObjectFieldForm;
use DeepWebSolutions\Framework\Settings\MetaField\ValueObjects\FieldGroup;
use DeepWebSolutions\Framework\Settings\MetaField\ValueObjects\UserProfileFieldGroup;
use DeepWebSolutions\Framework\Settings\Schema\Exceptions\DuplicateSettingsFieldException;
use DeepWebSolutions\Framework\Settings\Schema\Exceptions\InvalidSettingsFieldException;
use DeepWebSolutions\Framework\Settings\Schema\Field\FieldProcessor;
use DeepWebSolutions\Framework\Settings\Schema\Field\FieldRenderer;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsField;
use DeepWebSolutions\Framework\Storage\ObjectMeta\MetadataRepository;
use DeepWebSolutions\Framework\Storage\ObjectMeta\MetaType;
use DeepWebSolutions\Framework\Storage\ObjectMeta\ObjectMetaRepositoryInterface;

use function DeepWebSolutions\Framework\Settings\Schema\field_label_html;

/**
 * Surface that mounts a field group onto the WordPress user-profile screens and stores its fields as user meta.
 *
 * Renders the group on the profile edit screens — always when an administrator edits another user
 * (edit_user_profile), and on a user's own profile (show_user_profile) unless the descriptor restricts
 * it — and saves on the matching update hooks, reading and writing user meta through a metadata
 * repository. The current user must be able to edit the target user; the per-field gate and the nonce
 * are the shared form engine's responsibility.
 *
 * Beyond registration, the surface exposes field-addressed CRUD over the same storage keys and value
 * semantics the form path applies — get/set/has/delete by group and field id — plus meta_keys() for the
 * consumer's uninstall cleanup. Object fields are revoke-based, so reads never fall back to the field's
 * declared default.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class UserProfileFieldSurface {
	// region FIELDS AND CONSTANTS

	/**
	 * Registered profile groups keyed by group id.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     array<string, UserProfileFieldGroup>
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
		protected ObjectMetaRepositoryInterface $repository = new MetadataRepository( MetaType::User ),
	) {
		$this->form = new ObjectFieldForm( $this->repository, $renderer, $processor );
	}

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
		$this->registrations[ $profile->group->id ] = $profile;

		\add_action( 'edit_user_profile', array( $this, 'render_other_profile' ) );
		\add_action( 'edit_user_profile_update', array( $this, 'save_other_profile' ) );

		if ( $profile->on_own_profile ) {
			\add_action( 'show_user_profile', array( $this, 'render_own_profile' ) );
			\add_action( 'personal_options_update', array( $this, 'save_own_profile' ) );
		}
	}

	/**
	 * Retrieves a field's stored value for a user — {@see ObjectFieldForm::get()} for the read semantics.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   FieldGroup $group         Group that declares the field.
	 * @param   int        $user_id       User to read.
	 * @param   string     $field_id      Field whose value to read.
	 * @param   mixed      $default_value Value to return when nothing is stored.
	 *
	 * @throws  DuplicateSettingsFieldException If two of the group's fields share an id or storage key.
	 * @throws  InvalidSettingsFieldException If the group declares no field with the given id.
	 *
	 * @return  mixed
	 */
	public function get( FieldGroup $group, int $user_id, string $field_id, mixed $default_value = null ): mixed {
		return $this->form->get( $group, $user_id, $field_id, $default_value );
	}

	/**
	 * Persists a field's value for a user — {@see ObjectFieldForm::set()} for the store-or-revoke semantics.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   FieldGroup $group    Group that declares the field.
	 * @param   int        $user_id  User to write.
	 * @param   string     $field_id Field whose value to write.
	 * @param   mixed      $value    Value to persist.
	 *
	 * @throws  DuplicateSettingsFieldException If two of the group's fields share an id or storage key.
	 * @throws  InvalidSettingsFieldException If the group declares no field with the given id.
	 */
	public function set( FieldGroup $group, int $user_id, string $field_id, mixed $value ): void {
		$this->form->set( $group, $user_id, $field_id, $value );
	}

	/**
	 * Whether a real value is stored for a field on a user — {@see ObjectFieldForm::has()}.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   FieldGroup $group    Group that declares the field.
	 * @param   int        $user_id  User to check.
	 * @param   string     $field_id Field to check.
	 *
	 * @throws  DuplicateSettingsFieldException If two of the group's fields share an id or storage key.
	 * @throws  InvalidSettingsFieldException If the group declares no field with the given id.
	 *
	 * @return  bool
	 */
	public function has( FieldGroup $group, int $user_id, string $field_id ): bool {
		return $this->form->has( $group, $user_id, $field_id );
	}

	/**
	 * Deletes a field's stored value from a user — {@see ObjectFieldForm::delete()}.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   FieldGroup $group    Group that declares the field.
	 * @param   int        $user_id  User to clear.
	 * @param   string     $field_id Field to clear.
	 *
	 * @throws  DuplicateSettingsFieldException If two of the group's fields share an id or storage key.
	 * @throws  InvalidSettingsFieldException If the group declares no field with the given id.
	 *
	 * @return  bool True if a value was deleted, false if none existed.
	 */
	public function delete( FieldGroup $group, int $user_id, string $field_id ): bool {
		return $this->form->delete( $group, $user_id, $field_id );
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
	 * Renders every registered group on another user's profile edit screen.
	 * Hooked to edit_user_profile.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   \WP_User $user User whose profile is being edited.
	 */
	public function render_other_profile( \WP_User $user ): void {
		foreach ( $this->registrations as $profile ) {
			$this->render_profile( $profile->group, $user );
		}
	}

	/**
	 * Saves every registered group when another user's profile is updated.
	 * Hooked to edit_user_profile_update.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $user_id User whose profile was submitted.
	 */
	public function save_other_profile( int $user_id ): void {
		foreach ( $this->registrations as $profile ) {
			$this->save_profile( $profile->group, $user_id );
		}
	}

	/**
	 * Renders the registered groups whose descriptor allows a user's own profile.
	 * Hooked to show_user_profile.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   \WP_User $user User whose profile is being edited.
	 */
	public function render_own_profile( \WP_User $user ): void {
		foreach ( $this->registrations as $profile ) {
			if ( $profile->on_own_profile ) {
				$this->render_profile( $profile->group, $user );
			}
		}
	}

	/**
	 * Saves the registered groups whose descriptor allows a user's own profile.
	 * Hooked to personal_options_update.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $user_id User whose profile was submitted.
	 */
	public function save_own_profile( int $user_id ): void {
		foreach ( $this->registrations as $profile ) {
			if ( $profile->on_own_profile ) {
				$this->save_profile( $profile->group, $user_id );
			}
		}
	}

	// endregion

	// region HELPERS

	/**
	 * Renders the group inside a titled form table when the current user can edit the target user.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   FieldGroup $group Group to render.
	 * @param   \WP_User   $user  User whose profile is being edited.
	 */
	protected function render_profile( FieldGroup $group, \WP_User $user ): void {
		if ( ! \current_user_can( 'edit_user', $user->ID ) ) {
			return;
		}

		echo '<h2>' . \esc_html( $group->title ) . '</h2><table class="form-table" role="presentation">';
		$this->form->render( $group, $user->ID, $this->row() );
		echo '</table>';
	}

	/**
	 * Saves the group when the current user can edit the target user.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   FieldGroup $group   Group to save.
	 * @param   int        $user_id User whose profile was submitted.
	 */
	protected function save_profile( FieldGroup $group, int $user_id ): void {
		if ( ! \current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		$this->form->save( $group, $user_id );
	}

	/**
	 * The row closure wrapping each control in a form-table row, its label bound to the control's DOM id.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  \Closure
	 */
	protected function row(): \Closure {
		return static fn ( SettingsField $field, string $control, string $control_id ): string =>
			'<tr><th scope="row">' . field_label_html( $field, $control_id ) . '</th><td>' . $control . '</td></tr>';
	}

	// endregion
}
