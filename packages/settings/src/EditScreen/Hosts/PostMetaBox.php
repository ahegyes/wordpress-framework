<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\EditScreen\Hosts;

use DeepWebSolutions\Framework\Settings\EditScreen\ObjectForm;
use DeepWebSolutions\Framework\Settings\EditScreen\ObjectFormHostInterface;
use DeepWebSolutions\Framework\Settings\EditScreen\ObjectStoreInterface;
use DeepWebSolutions\Framework\Settings\EditScreen\Stores\MetadataStore;

\defined( 'ABSPATH' ) || exit;

/**
 * Hosts an object form in a meta box on the edit screens of posts of the given types, with the field rows stacked so that they fit any of the box's contexts.
 *
 * A row is a block, so a field's render callable may return block content.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @phpstan-import-type Row from ObjectFormHostInterface
 */
final class PostMetaBox implements ObjectFormHostInterface {
	// region FIELDS AND CONSTANTS

	/**
	 * The form the box hosts.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     ObjectForm
	 */
	protected ObjectForm $form;

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @phpstan-param 'high'|'core'|'default'|'low' $priority
	 *
	 * @param   list<string> $post_types The post types whose edit screens show the box.
	 * @param   string       $context    Optional. The screen area the box shows in, as add_meta_box() takes it. Default is advanced.
	 * @param   string       $priority   Optional. The box's priority within its context, as add_meta_box() takes it. Default is default.
	 */
	public function __construct(
		protected array $post_types,
		protected string $context = 'advanced',
		protected string $priority = 'default'
	) {}

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
		\add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 10, 2 );
		\add_action( 'save_post', array( $this, 'save_meta_box' ), 10, 2 );
		\add_filter( 'wp_refresh_nonces', array( $this, 'refresh_nonces' ), 11, 2 );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function get_object_capability(): string {
		return 'edit_post';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function get_default_store(): ObjectStoreInterface {
		return new MetadataStore( 'post' );
	}

	/**
	 * Prints the form's rows stacked, a group's title as plain text, and needs no hosted form, so another host can print through it.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string    $title The form's title, escaped, which the box's own title shows instead.
	 * @param   list<Row> $rows  The rows, one per field.
	 */
	#[\Override]
	public function render_rows( string $title, array $rows ): void {
		foreach ( $rows as $row ) {
			$heading = \is_null( $row['label_for'] ) ? '<span class="post-attributes-label">' . $row['title'] . '</span>' : '<label class="post-attributes-label" for="' . \esc_attr( $row['label_for'] ) . '">' . $row['title'] . '</label>';
			echo '<div class="form-field"><p class="post-attributes-label-wrapper">' . $heading . '</p>' . $row['control'] . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The form escapes titles and controls.
		}
	}

	// endregion

	// region HOOKS

	/**
	 * Adds the box to the edit screen of a post of the box's types when the form shows for it.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $post_type The edited object's post type.
	 * @param   mixed  $post      The edited object, which is a comment on the comment edit screen.
	 */
	public function add_meta_box( string $post_type, mixed $post ): void {
		if ( ! $post instanceof \WP_Post || ! \in_array( $post_type, $this->post_types, true ) || ! $this->form->can_render( $post->ID ) ) {
			return;
		}

		// WordPress prints a box's title as markup and the block editor lists it as text, so the title goes in as the form defines it.
		\add_meta_box( $this->form->get_id(), $this->form->get_title( $post->ID ), array( $this, 'render_meta_box' ), $post_type, $this->context, $this->priority );
	}

	/**
	 * Prints the form inside the box and, on the block editor's screen, the script that copies the form's refreshed nonce into its field.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   \WP_Post $post The edited post.
	 */
	public function render_meta_box( \WP_Post $post ): void {
		$this->form->render( $post->ID );

		// The block editor's heartbeat script sets only core's nonces, by name, while the classic editor's sets every one the heartbeat returns.
		if ( true === \get_current_screen()?->is_block_editor() ) {
			foreach ( \array_keys( $this->form->get_refreshed_nonces( $post->ID ) ) as $field ) {
				$field = \esc_js( $field );
				\wp_add_inline_script( 'heartbeat', "jQuery( document ).on( 'heartbeat-tick', function ( event, data ) { var nonces = data['wp-refresh-metabox-loader-nonces']; if ( nonces && nonces.replace && nonces.replace['$field'] ) { jQuery( '#$field' ).val( nonces.replace['$field'] ); } } );" );
			}
		}
	}

	/**
	 * Saves the form for a saved post of the box's types, which leaves out revisions and autosaves, whose post type is revision.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int      $post_id The saved post's id.
	 * @param   \WP_Post $post    The saved post.
	 */
	public function save_meta_box( int $post_id, \WP_Post $post ): void {
		if ( \in_array( $post->post_type, $this->post_types, true ) ) {
			$this->form->save( $post_id );
		}
	}

	/**
	 * Adds the form's nonce to the nonces the heartbeat refreshes for a post of the box's types, in the classic editor and in the block editor's meta box loader.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<mixed> $response The heartbeat response.
	 * @param   array<mixed> $data     The data the heartbeat sent.
	 *
	 * @return  array<mixed>
	 */
	public function refresh_nonces( array $response, array $data ): array {
		foreach ( array( 'wp-refresh-post-nonces', 'wp-refresh-metabox-loader-nonces' ) as $key ) {
			$refresh = $response[ $key ] ?? null;
			$request = $data[ $key ] ?? null;
			if ( ! \is_array( $refresh ) || ! isset( $refresh['replace'] ) || ! \is_array( $refresh['replace'] ) || ! \is_array( $request ) || ! \is_numeric( $request['post_id'] ?? null ) ) {
				continue;
			}

			$post_id = (int) $request['post_id'];
			if ( \in_array( \get_post_type( $post_id ), $this->post_types, true ) ) {
				$refresh['replace'] += $this->form->get_refreshed_nonces( $post_id );
				$response[ $key ]    = $refresh;
			}
		}

		return $response;
	}

	// endregion
}
