<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings;

use DeepWebSolutions\Framework\Shared\Exception\LogicException;

\defined( 'ABSPATH' ) || exit;

/**
 * Hosts an object form as a titled section of the edit screens of terms of the given taxonomies, after WordPress's own fields.
 *
 * The screen that adds a term creates it over admin-ajax without a reload, so no term exists to bind the form's nonce to, and the form shows only once the term exists. A row's control sits in a table cell, so a field's render callable may return block content.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @phpstan-import-type Row from ObjectFormHostInterface
 */
final class TermForm implements ObjectFormHostInterface {
	// region FIELDS AND CONSTANTS

	/**
	 * The form the edit screens host.
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
	 * @param   list<string> $taxonomies The taxonomies whose term edit screens show the form.
	 */
	public function __construct(
		protected array $taxonomies
	) {}

	// endregion

	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @throws  LogicException Thrown when the edit screens already host another form.
	 */
	#[\Override]
	public function register_hooks( ObjectForm $form ): void {
		if ( isset( $this->form ) && $form !== $this->form ) {
			throw new LogicException( "Term form of '{$this->form->get_id()}' cannot also host '{$form->get_id()}'." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing, never rendered.
		}

		$this->form = $form;
		foreach ( $this->taxonomies as $taxonomy ) {
			\add_action( "{$taxonomy}_edit_form", array( $this, 'render_section' ) );
			\add_action( "edited_$taxonomy", array( $this, 'save_term' ) );
		}
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function get_object_capability(): string {
		return 'edit_term';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function get_default_store(): ObjectStoreInterface {
		return new MetadataStore( 'term' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function render_rows( string $title, array $rows ): void {
		echo '<h2>' . $title . '</h2><table class="form-table" role="presentation">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The form escapes its title.
		foreach ( $rows as $row ) {
			if ( \is_null( $row['label_for'] ) ) {
				echo '<tr><th scope="row">' . $row['title'] . '</th><td><fieldset><legend class="screen-reader-text"><span>' . $row['title'] . '</span></legend>' . $row['control'] . '</fieldset></td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The form escapes titles and controls.
			} else {
				echo '<tr><th scope="row"><label for="' . \esc_attr( $row['label_for'] ) . '">' . $row['title'] . '</label></th><td>' . $row['control'] . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The form escapes titles and controls.
			}
		}
		echo '</table>';
	}

	// endregion

	// region HOOKS

	/**
	 * Prints the form's section for the edited term.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   \WP_Term $term The edited term.
	 */
	public function render_section( \WP_Term $term ): void {
		$this->form->render( $term->term_id );
	}

	/**
	 * Saves the form for the updated term.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $term_id The updated term's id.
	 */
	public function save_term( int $term_id ): void {
		$this->form->save( $term_id );
	}

	// endregion
}
