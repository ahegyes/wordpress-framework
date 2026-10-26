<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\EditScreen;

use DeepWebSolutions\Framework\Core\AdminNotice\AdminNotices;
use DeepWebSolutions\Framework\Core\AdminNotice\Notice;
use DeepWebSolutions\Framework\Settings\Forms;
use DeepWebSolutions\Framework\Shared\Exception\InvalidArgumentException;
use DeepWebSolutions\Framework\Shared\Exception\LogicException;

\defined( 'ABSPATH' ) || exit;

/**
 * Adds a form of field arrays to the screen that edits an object, and stores the accepted fields as the object's data.
 *
 * Its host decides where the form shows and when it saves, and its store keeps the values, by default the host's own. The form shows only to a user who may edit the object and holds the definition's capability, carries a nonce bound to the object, and reports each rejection of a save as a one-shot notice for the user, which replaces the notice of the field's earlier rejection. Controls post under the form's id, so they never meet the screen's own inputs, and each field stores under its id.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @phpstan-import-type Field from Forms
 * @phpstan-type Form array{title: string, capability: string, fields: list<Field>}
 */
final class ObjectForm {
	// region FIELDS AND CONSTANTS

	/**
	 * The form of each object the request has resolved, with its fields filtered, keyed by the object's id.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     array<int, Form>
	 */
	protected array $forms = array();

	/**
	 * The ids of the objects the request saved the form for.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     list<int>
	 */
	protected array $saved = array();

	/**
	 * The form each host serves, keyed by the host, so that no host serves two forms, including one a consumer writes.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     \WeakMap<ObjectFormHostInterface, ObjectForm>
	 */
	protected static \WeakMap $hosted;

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string                        $id         The form's id, which starts with the plugin's prefix and names the form's controls, nonce and fields filter.
	 * @param   ObjectFormDefinitionInterface $definition The definition, which the form asks for once per object, on first use after init.
	 * @param   ObjectFormHostInterface       $host       The host that shows and saves the form.
	 * @param   AdminNotices                  $notices    The plugin's admin notices, which report the rejections.
	 * @param   ObjectStoreInterface|null     $store      Optional. The store of the form's values. Default is null, for the host's store.
	 *
	 * @throws  InvalidArgumentException Thrown when the id is not a valid key.
	 */
	public function __construct(
		protected string $id,
		protected ObjectFormDefinitionInterface $definition,
		protected ObjectFormHostInterface $host,
		protected AdminNotices $notices,
		protected ?ObjectStoreInterface $store = null
	) {
		Forms::check_helper_id( $id );
		self::$hosted ??= new \WeakMap();
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
	 * Returns the form's title for the object.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $object_id The object's id.
	 *
	 * @return  string
	 */
	public function get_title( int $object_id ): string {
		return $this->get_form( $object_id )['title'];
	}

	// endregion

	// region METHODS

	/**
	 * Registers the host's hooks.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @throws  LogicException Thrown when the host already serves another form.
	 *
	 * @return  void
	 */
	public function register_hooks(): void {
		$hosted = self::$hosted[ $this->host ] ?? $this;
		if ( $hosted !== $this ) {
			throw new LogicException( "The host of object form '{$hosted->get_id()}' cannot also host '$this->id'." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing, never rendered.
		}

		self::$hosted[ $this->host ] = $this;
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
		return $this->can_edit( $object_id ) && array() !== \array_column( $this->get_form( $object_id )['fields'], 'id' );
	}

	/**
	 * Prints the form's nonce and rows for the object, when the form shows for it.
	 *
	 * A field's control carries the stored value, or else the field's default.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $object_id The object's id.
	 *
	 * @return  void
	 */
	public function render( int $object_id ): void {
		if ( ! $this->can_render( $object_id ) ) {
			return;
		}

		$fields = $this->get_form( $object_id )['fields'];
		$rows   = array();
		foreach ( Forms::get_rows( $fields, $this->get_store()->get_values( $object_id, \array_column( $fields, 'id' ) ), $this->id ) as $row ) {
			$control = Forms::render_control( $row['field'], $row['value'], $row['name'], $row['id'] );
			if ( '' !== $control ) {
				$rows[] = array(
					'title'     => esc_html( $row['field']['title'] ?? '' ),
					'label_for' => $row['label_for'] ?? null,
					'control'   => $control,
				);
			}
		}

		wp_nonce_field( $this->get_nonce_action( $object_id ), $this->get_nonce_name(), false );
		$this->host->render_rows( esc_html( $this->get_title( $object_id ) ), $rows );
	}

	/**
	 * Stores the accepted fields of the request's submission for the object, and reports each rejection as a one-shot error notice for the current user.
	 *
	 * Nothing changes unless the request carries the form's nonce for the object, which binds it to the object as WordPress core binds its edit forms, so a save that fires for a revision or another object in the same request is refused, and the current user may edit the object and holds the definition's capability. A field the form did not post, because it was not rendered or is disabled, keeps its value, as a rejected one does. The form saves once per object and request, because a save that updates the object again fires the host's save hook again with the same submission.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $object_id The object's id.
	 *
	 * @return  void
	 */
	public function save( int $object_id ): void {
		// Saves that carry no form for the object, such as REST, cron and quick edit, return before the definition is evaluated.
		$nonce = \is_string( $_POST[ $this->get_nonce_name() ] ?? null ) ? sanitize_text_field( wp_unslash( $_POST[ $this->get_nonce_name() ] ) ) : '';
		if ( \in_array( $object_id, $this->saved, true ) || false === wp_verify_nonce( $nonce, $this->get_nonce_action( $object_id ) ) || ! $this->can_edit( $object_id ) ) {
			return;
		}

		$this->saved[] = $object_id;

		$submitted = \is_array( $_POST[ $this->id ] ?? null ) ? wp_unslash( $_POST[ $this->id ] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Forms::sanitize_fields() sanitizes each field.
		$fields    = $this->get_form( $object_id )['fields'];

		$result = Forms::sanitize_fields( $fields, $submitted );
		if ( array() !== $result['values'] ) {
			$this->get_store()->update_values( $object_id, $result['values'] );
		}

		foreach ( $fields as $field ) {
			if ( isset( $field['id'], $result['errors'][ $field['id'] ] ) ) {
				$this->notices->add( new Notice( 'rejection-' . \md5( "$this->id|{$field['id']}" ), esc_html( Forms::get_rejection_message( Forms::get_title( $field ), $result['errors'][ $field['id'] ] ) ) ) );
			}
		}
	}

	/**
	 * Returns the form's nonce for the object keyed by its field's HTML id, as WordPress's heartbeat refreshes nonces, or nothing for a current user who may not edit the object with the form.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $object_id The object's id.
	 *
	 * @return  array<string, string>
	 */
	public function get_refreshed_nonces( int $object_id ): array {
		return $this->can_edit( $object_id ) ? array( $this->get_nonce_name() => wp_create_nonce( $this->get_nonce_action( $object_id ) ) ) : array();
	}

	// endregion

	// region HELPERS

	/**
	 * Returns the form for the object, with its fields as other plugins filter them, resolving it once per object.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $object_id The object's id.
	 *
	 * @return  Form
	 */
	protected function get_form( int $object_id ): array {
		if ( ! isset( $this->forms[ $object_id ] ) ) {
			$form = $this->definition->get_object_form( $this->id, $object_id );

			/**
			 * Filters the fields of the object form for one object, so that other plugins can add fields.
			 *
			 * @since   2.0.0
			 * @version 2.0.0
			 *
			 * @param   list<Field> $fields    The form's fields for the object.
			 * @param   int         $object_id The id of the object being edited or saved.
			 */
			$form['fields'] = apply_filters( "{$this->id}_fields", $form['fields'], $object_id );

			$this->forms[ $object_id ] = $form;
		}

		return $this->forms[ $object_id ];
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
	 * The definition's capability matters because WordPress grants every user edit_user on themself.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $object_id The object's id.
	 *
	 * @return  bool
	 */
	protected function can_edit( int $object_id ): bool {
		return current_user_can( $this->host->get_object_capability(), $object_id ) && current_user_can( $this->get_form( $object_id )['capability'] );
	}

	/**
	 * Returns the name of the form's nonce field.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  string
	 */
	protected function get_nonce_name(): string {
		return "{$this->id}_nonce";
	}

	/**
	 * Returns the action of the form's nonce for the object.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $object_id The object's id.
	 *
	 * @return  string
	 */
	protected function get_nonce_action( int $object_id ): string {
		return "{$this->id}_nonce_$object_id";
	}

	// endregion
}
