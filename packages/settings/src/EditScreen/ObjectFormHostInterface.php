<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\EditScreen;

\defined( 'ABSPATH' ) || exit;

/**
 * Shows an object form where WordPress edits the host's objects and saves it when they are saved.
 *
 * A host serves one form.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @phpstan-type Row array{title: string, label_for: string|null, control: string}
 */
interface ObjectFormHostInterface {
	/**
	 * Registers the hooks that print the form for an object being edited and save it when the object is saved, through the form's render() and save() with the object's id.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   ObjectForm $form The form the host serves.
	 */
	public function register_hooks( ObjectForm $form ): void;

	/**
	 * Returns the capability that edits one of the host's objects, which the form checks against the object's id.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  string
	 */
	public function get_object_capability(): string;

	/**
	 * Returns the store of the host's objects, which the form uses when it is given none.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  ObjectStoreInterface
	 */
	public function get_default_store(): ObjectStoreInterface;

	/**
	 * Prints the form's rows in the host's layout.
	 *
	 * A row's title and control are escaped markup. Its label_for is the HTML id the row's label points at, or null for a group, whose control carries no legend, so the row names it with one.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string    $title The form's title, escaped.
	 * @param   list<Row> $rows  The rows, one per field.
	 */
	public function render_rows( string $title, array $rows ): void;
}
