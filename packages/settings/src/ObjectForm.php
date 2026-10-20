<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings;

use DeepWebSolutions\Framework\Shared\Exception\InvalidArgumentException;

\defined( 'ABSPATH' ) || exit;

/**
 * Adds a form of field arrays to the screen that edits an object, and stores the accepted fields as the object's data.
 *
 * Its host decides where the form shows and when it saves, and its store keeps the values, by default the host's own. The form shows only to a user who may edit the object and holds the definition's capability, carries a nonce bound to the object, and keeps each rejection of a save for the user's next admin screen. Controls post under the form's id, so they never meet the screen's own inputs, and each field stores under its id.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @phpstan-import-type Field from Forms
 * @phpstan-type Form array{title: string, capability: string, fields: list<Field>|\Closure(int): list<Field>}
 */
final class ObjectForm {
	// region FIELDS AND CONSTANTS

	/**
	 * The form's definition, evaluated on first use.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     Form|null
	 */
	protected ?array $form = null;

	/**
	 * The fields of each object the request has resolved, keyed by the object's id.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     array<int, list<Field>>
	 */
	protected array $fields = array();

	/**
	 * The ids of the objects the request saved the form for.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     list<int>
	 */
	protected array $saved = array();

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string                    $id         The form's id, which starts with the plugin's prefix and names the form's controls, nonce, fields filter and stored rejections.
	 * @param   \Closure(): Form          $definition The definition, which is evaluated on first use after init and must not throw.
	 * @param   ObjectFormHostInterface   $host       The host that shows and saves the form.
	 * @param   ObjectStoreInterface|null $store      Optional. The store of the form's values. Default is null, for the host's store.
	 *
	 * @throws  InvalidArgumentException Thrown when the id is not a valid key.
	 */
	public function __construct(
		protected string $id,
		protected \Closure $definition,
		protected ObjectFormHostInterface $host,
		protected ?ObjectStoreInterface $store = null
	) {
		if ( ! self::is_valid_key( $id ) ) {
			throw new InvalidArgumentException( "Object form id '$id' is not a lowercase letter, after an optional underscore, followed by lowercase letters, digits, underscores and hyphens." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing, never rendered.
		}
	}

	// endregion

	// region GETTERS

	/**
	 * Returns the form's id.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  string
	 */
	public function get_id(): string {
		return $this->id;
	}

	/**
	 * Returns the form's title.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  string
	 */
	public function get_title(): string {
		return $this->get_form()['title'];
	}

	// endregion

	// region METHODS

	/**
	 * Registers the hooks that print the rejections a save kept, and the host's hooks.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function register_hooks(): void {
		\add_action( 'all_admin_notices', array( $this, 'render_rejections' ) );
		$this->host->register_hooks( $this );
	}

	/**
	 * Returns whether the form shows for the object, which needs fields for it and a current user who may edit it and holds the definition's capability.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $object_id The object's id.
	 *
	 * @return  bool
	 */
	public function can_render( int $object_id ): bool {
		return $this->can_edit( $object_id ) && array() !== \array_column( $this->get_fields( $object_id ), 'id' );
	}

	/**
	 * Prints the form's nonce and rows for the object, when the form shows for it.
	 *
	 * A field's control carries the stored value, or else the field's default, and a group's control carries no legend, because its row names it.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $object_id The object's id.
	 */
	public function render( int $object_id ): void {
		if ( ! $this->can_render( $object_id ) ) {
			return;
		}

		$fields = $this->get_fields( $object_id );
		$values = $this->get_store()->get_values( $object_id, \array_column( $fields, 'id' ) );
		$rows   = array();
		foreach ( $fields as $field ) {
			if ( ! isset( $field['id'] ) ) {
				continue;
			}

			$field['title'] = self::get_field_title( $field );
			$id             = "{$this->id}.{$field['id']}";
			$group          = \in_array( $field['type'], array( 'radio', 'checkboxes' ), true ) || ( 'checkbox' === $field['type'] && '' !== ( $field['desc'] ?? '' ) );
			$value          = \array_key_exists( $field['id'], $values ) ? $values[ $field['id'] ] : ( $field['default'] ?? '' );
			$control        = Forms::render_control( $group ? array( 'title' => '' ) + $field : $field, $value, "{$this->id}[{$field['id']}]", $id );
			if ( '' !== $control ) {
				$rows[] = array(
					'title'     => \esc_html( $field['title'] ),
					'label_for' => $group ? null : $id,
					'control'   => $control,
				);
			}
		}

		\wp_nonce_field( "{$this->id}_nonce_$object_id", "{$this->id}_nonce", false );
		$this->host->render_rows( \esc_html( $this->get_title() ), $rows );
	}

	/**
	 * Stores the accepted fields of the request's submission for the object, and keeps each rejection for the current user's next admin screen.
	 *
	 * Nothing changes unless the request carries the form's nonce for the object and the current user may edit the object and holds the definition's capability. A field the form did not post, because it was not rendered or is disabled, keeps its value, as a rejected one does. The form saves once per object and request, because a save that updates the object again fires the host's save hook again with the same submission.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $object_id The object's id.
	 */
	public function save( int $object_id ): void {
		// Saves that carry no form, such as REST, cron and quick edit, return before the definition is evaluated.
		if ( ! isset( $_POST[ "{$this->id}_nonce" ] ) || \in_array( $object_id, $this->saved, true ) || ! Forms::verify_object_form( "{$this->id}_nonce", $object_id, $this->host->get_object_capability(), $this->get_form()['capability'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Forms::verify_object_form() verifies the nonce.
			return;
		}

		$this->saved[] = $object_id;
		$submitted     = \is_array( $_POST[ $this->id ] ?? null ) ? \wp_unslash( $_POST[ $this->id ] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Forms::verify_object_form() verified the nonce, and Forms::sanitize_fields() sanitizes each field.
		$fields        = $this->get_fields( $object_id );
		$result        = Forms::sanitize_fields( $fields, $submitted );
		if ( array() !== $result['values'] ) {
			$this->get_store()->update_values( $object_id, $result['values'] );
		}

		$messages = array();
		foreach ( $fields as $field ) {
			if ( isset( $field['id'], $result['errors'][ $field['id'] ] ) ) {
				$messages[] = self::get_rejection_message( $field, $result['errors'][ $field['id'] ] );
			}
		}
		if ( array() !== $messages ) {
			$user_id = \get_current_user_id();
			$kept    = \get_user_option( "{$this->id}_rejections", $user_id );
			// The metadata API unslashes what it is given, which would strip a message's own backslashes.
			\update_user_option( $user_id, "{$this->id}_rejections", \wp_slash( \array_merge( \is_array( $kept ) ? $kept : array(), $messages ) ) );
		}
	}

	// endregion

	// region HOOKS

	/**
	 * Prints the rejections the current user's saves kept on this site, once, outside the block editor.
	 *
	 * The block editor's styles hide classic notices, and its meta box save renders the edit screen in a request nobody sees.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function render_rejections(): void {
		// The screen API loads only in the admin.
		if ( \function_exists( 'get_current_screen' ) && true === \get_current_screen()?->is_block_editor() ) {
			return;
		}

		$messages = \get_user_option( "{$this->id}_rejections" );
		if ( ! \is_array( $messages ) ) {
			return;
		}

		\delete_user_option( \get_current_user_id(), "{$this->id}_rejections" );
		foreach ( $messages as $message ) {
			if ( \is_string( $message ) ) {
				\wp_admin_notice(
					\esc_html( $message ),
					array(
						'type'        => 'error',
						'dismissible' => true,
					)
				);
			}
		}
	}

	// endregion

	// region HELPERS

	/**
	 * Returns the form's definition, evaluating it on first use.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  Form
	 */
	protected function get_form(): array {
		return $this->form ??= ( $this->definition )();
	}

	/**
	 * Returns the form's fields for the object, as other plugins filter them, resolving them once per object.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $object_id The object's id.
	 *
	 * @return  list<Field>
	 */
	protected function get_fields( int $object_id ): array {
		if ( ! isset( $this->fields[ $object_id ] ) ) {
			$fields = $this->get_form()['fields'];

			/**
			 * Filters the fields of the object form for one object, so that other plugins can add fields.
			 *
			 * @since   2.0.0
			 * @version 2.0.0
			 *
			 * @param   list<Field> $fields    The form's fields for the object.
			 * @param   int         $object_id The id of the object being edited or saved.
			 */
			$this->fields[ $object_id ] = \apply_filters( "{$this->id}_fields", $fields instanceof \Closure ? $fields( $object_id ) : $fields, $object_id );
		}

		return $this->fields[ $object_id ];
	}

	/**
	 * Returns the store of the form's values.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  ObjectStoreInterface
	 */
	protected function get_store(): ObjectStoreInterface {
		return $this->store ??= $this->host->get_default_store();
	}

	/**
	 * Returns whether the current user may edit the object and holds the definition's capability.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $object_id The object's id.
	 *
	 * @return  bool
	 */
	protected function can_edit( int $object_id ): bool {
		return \current_user_can( $this->host->get_object_capability(), $object_id ) && \current_user_can( $this->get_form()['capability'] );
	}

	/**
	 * Returns the notice for a rejected field, which names the field and adds the rejection's message when it has one.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   Field     $field The field.
	 * @param   \WP_Error $error The rejection.
	 *
	 * @return  string
	 */
	protected static function get_rejection_message( array $field, \WP_Error $error ): string {
		$name    = self::get_field_title( $field );
		$message = $error->get_error_message();
		if ( '' === $message ) {
			/* translators: %s: The field's title. */
			return \sprintf( \__( '%s was not saved because its value is not valid, so it keeps its previous value.', 'wp-framework' ), $name );
		}

		/* translators: 1: The field's title. 2: What to enter instead. */
		return \sprintf( \__( '%1$s was not saved, so it keeps its previous value. %2$s', 'wp-framework' ), $name, $message );
	}

	/**
	 * Returns the field's title, or its id when the title is empty, so that every row and rejection names its field.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   Field $field The field.
	 *
	 * @return  string
	 */
	protected static function get_field_title( array $field ): string {
		return '' !== ( $field['title'] ?? '' ) ? $field['title'] : ( $field['id'] ?? '' );
	}

	/**
	 * Returns whether the key is valid as a helper id.
	 *
	 * The pattern accepts every v1 option key, meta key, prefix and slug, so that ports keep their keys.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $key The key.
	 *
	 * @return  bool
	 */
	protected static function is_valid_key( string $key ): bool {
		return 1 === \preg_match( '/\A_?[a-z][a-z0-9_-]*\z/', $key );
	}

	// endregion
}
