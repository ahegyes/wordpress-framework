<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\EditScreen\Hosts;

use DeepWebSolutions\Framework\Settings\EditScreen\FormTable;
use DeepWebSolutions\Framework\Settings\EditScreen\ObjectForm;
use DeepWebSolutions\Framework\Settings\EditScreen\ObjectFormHostInterface;
use DeepWebSolutions\Framework\Settings\EditScreen\ObjectStoreInterface;
use DeepWebSolutions\Framework\Settings\EditScreen\Stores\MetadataStore;

\defined( 'ABSPATH' ) || exit;

/**
 * Hosts an object form as a titled section of the profile screens, the user's own and the one that edits another user.
 *
 * The form saves before WordPress validates the profile's own fields, so its fields are stored even when WordPress rejects one of its own. A row's control sits in a table cell, so a field's render callable may return block content.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @phpstan-import-type Row from ObjectFormHostInterface
 */
final class UserProfile implements ObjectFormHostInterface {
	// region FIELDS AND CONSTANTS

	/**
	 * The form the profile hosts.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     ObjectForm
	 */
	protected ObjectForm $form;

	// endregion

	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function register_hooks( ObjectForm $form ): void {
		$this->form = $form;
		\add_action( 'show_user_profile', array( $this, 'render_section' ) );
		\add_action( 'edit_user_profile', array( $this, 'render_section' ) );
		\add_action( 'personal_options_update', array( $this, 'save_profile' ) );
		\add_action( 'edit_user_profile_update', array( $this, 'save_profile' ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function get_object_capability(): string {
		return 'edit_user';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function get_default_store(): ObjectStoreInterface {
		return new MetadataStore( 'user' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function render_rows( string $title, array $rows ): void {
		FormTable::render_rows( $title, $rows );
	}

	// endregion

	// region HOOKS

	/**
	 * Prints the form's section for the profile's user.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   \WP_User $user The profile's user.
	 */
	public function render_section( \WP_User $user ): void {
		$this->form->render( $user->ID );
	}

	/**
	 * Saves the form for the profile's user.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $user_id The profile's user id.
	 */
	public function save_profile( int $user_id ): void {
		$this->form->save( $user_id );
	}

	// endregion
}
