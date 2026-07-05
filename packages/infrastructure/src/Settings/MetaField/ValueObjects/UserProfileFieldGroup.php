<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\MetaField\ValueObjects;

/**
 * Descriptor for a group of fields on the user-profile surface.
 *
 * Wraps a {@see FieldGroup} with the one user-profile-specific choice: whether the fields also show on a
 * user editing their OWN profile (the show_user_profile surface) or only when an administrator edits
 * another user (edit_user_profile). The profile surface needs no further placement — it is global.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final readonly class UserProfileFieldGroup {
	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   FieldGroup $group Surface-agnostic group of fields to render on the profile.
	 * @param   bool       $on_own_profile Whether the fields also show when a user edits their own profile; false restricts them to an administrator editing another user.
	 */
	public function __construct(
		public FieldGroup $group,
		public bool $on_own_profile = true,
	) {}

	// endregion
}
