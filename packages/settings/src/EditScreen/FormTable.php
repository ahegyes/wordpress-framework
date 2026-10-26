<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\EditScreen;

\defined( 'ABSPATH' ) || exit;

/**
 * Prints an object form's rows as a titled form-table, as WordPress core lays out its profile and term screens.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @internal
 *
 * @phpstan-import-type Row from ObjectFormHostInterface
 */
final class FormTable {
	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	protected function __construct() {}

	// endregion

	// region METHODS

	/**
	 * Prints the form's title and its rows, a group's title as plain text, as do_settings_fields() prints a row without a label.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string    $title The form's title, escaped.
	 * @param   list<Row> $rows  The rows, one per field.
	 */
	public static function render_rows( string $title, array $rows ): void {
		echo '<h2>' . $title . '</h2><table class="form-table" role="presentation">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The form escapes its title.
		foreach ( $rows as $row ) {
			$heading = \is_null( $row['label_for'] ) ? $row['title'] : '<label for="' . esc_attr( $row['label_for'] ) . '">' . $row['title'] . '</label>';
			echo '<tr><th scope="row">' . $heading . '</th><td>' . $row['control'] . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The form escapes titles and controls.
		}
		echo '</table>';
	}

	// endregion
}
