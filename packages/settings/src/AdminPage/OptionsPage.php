<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\AdminPage;

use DeepWebSolutions\Framework\Settings\Forms;
use DeepWebSolutions\Framework\Shared\Exception\InvalidArgumentException;

\defined( 'ABSPATH' ) || exit;

/**
 * Adds a submenu page whose tabs each edit one option row through the Settings API.
 *
 * Each tab's form posts to options.php under a settings group named after its option, so WordPress checks the nonce and the page's capability before the page sanitizes the submission.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @phpstan-import-type Field from Forms
 * @phpstan-import-type FieldRow from Forms
 * @phpstan-type Tab array{slug: string, title: string, option: string, fields: list<Field>}
 * @phpstan-type Page array{parent: string, capability: string, page_title: string, menu_title: string, tabs: list<Tab>}
 */
final class OptionsPage {
	// region FIELDS AND CONSTANTS

	/**
	 * The page's definition, evaluated on first use and with its tabs filtered.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     Page|null
	 */
	protected ?array $page = null;

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string                         $id         The page's menu slug, which also prefixes its tabs filter.
	 * @param   OptionsPageDefinitionInterface $definition The definition, which the page asks for once, on first use after init.
	 *
	 * @throws  InvalidArgumentException Thrown when the id is not a valid key.
	 */
	public function __construct(
		protected string $id,
		protected OptionsPageDefinitionInterface $definition
	) {
		Forms::check_helper_id( $id );
	}

	// endregion

	// region METHODS

	/**
	 * Registers the hooks that add the page and its settings.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  void
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	// endregion

	// region HOOKS

	/**
	 * Adds the page to its parent menu for users holding its capability.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  void
	 */
	public function add_page(): void {
		$page = $this->get_page();
		add_submenu_page( $page['parent'], $page['page_title'], $page['menu_title'], $page['capability'], $this->id, array( $this, 'render_page' ) );
	}

	/**
	 * Registers each tab's option as a setting in a group of its own name and hooks the option's capability and sanitization.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  void
	 */
	public function register_settings(): void {
		foreach ( \array_unique( \array_column( $this->get_page()['tabs'], 'option' ) ) as $option ) {
			register_setting( $option, $option );
			add_filter( "option_page_capability_$option", array( $this, 'get_capability' ) );
			add_filter( "pre_update_option_$option", array( $this, 'sanitize_submission' ), 10, 3 );
		}
	}

	/**
	 * Returns the page's capability, which options.php requires to save its options.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  string
	 */
	public function get_capability(): string {
		return $this->get_page()['capability'];
	}

	/**
	 * Returns the row to store for the option's own options.php submission, in which only the accepted fields change.
	 *
	 * Other writes of the option pass through unchanged. A rejected field keeps its stored value and adds a settings error that names it.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed  $value     The submitted row, unslashed by options.php, or null when the form posted none of its fields.
	 * @param   mixed  $old_value The stored row.
	 * @param   string $option    The option's name.
	 *
	 * @return  mixed
	 */
	public function sanitize_submission( mixed $value, mixed $old_value, string $option ): mixed {
		// WordPress core reads option_page from the request, not only the body, and trims it, so the same reading decides whether the submission is this option's.
		$option_page = \is_string( $_REQUEST['option_page'] ?? null ) ? sanitize_text_field( wp_unslash( $_REQUEST['option_page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- options.php verifies the nonce before it saves.
		if ( 'options.php' !== ( $GLOBALS['pagenow'] ?? '' ) || $option_page !== $option ) {
			return $value;
		}

		$fields = array();
		foreach ( $this->get_page()['tabs'] as $tab ) {
			if ( $option === $tab['option'] ) {
				$fields = \array_merge( $fields, $tab['fields'] );
			}
		}

		$result = Forms::sanitize_fields( $fields, \is_array( $value ) ? $value : array() );
		foreach ( $fields as $field ) {
			if ( isset( $field['id'], $result['errors'][ $field['id'] ] ) ) {
				add_settings_error( $option, $field['id'], esc_html( Forms::get_rejection_message( Forms::get_title( $field ), $result['errors'][ $field['id'] ] ) ) );
			}
		}

		return array() === $result['values'] ? $old_value : \array_replace( \is_array( $old_value ) ? $old_value : array(), $result['values'] );
	}

	/**
	 * Prints the page with the form of the tab the request names, or of its first tab.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  void
	 */
	public function render_page(): void {
		$page = $this->get_page();
		$slug = \is_string( $_GET['tab'] ?? null ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Selects the tab to show and changes nothing.
		$tab  = \array_find( $page['tabs'], static fn ( array $tab ): bool => $slug === $tab['slug'] ) ?? $page['tabs'][0] ?? null;

		echo '<div class="wrap"><h1>' . esc_html( $page['page_title'] ) . '</h1>';
		if ( 'options-general.php' !== $page['parent'] ) { // WordPress core prints the settings errors on the pages under Settings itself.
			settings_errors();
		}
		if ( \is_null( $tab ) ) {
			echo '</div>';
			return;
		}

		if ( \count( $page['tabs'] ) > 1 ) {
			echo '<nav class="nav-tab-wrapper" aria-label="' . esc_attr( $page['page_title'] ) . '">';
			foreach ( $page['tabs'] as $link ) {
				$url = esc_url( add_query_arg( 'tab', $link['slug'], menu_page_url( $this->id, false ) ) );
				echo $link['slug'] === $tab['slug'] ? "<a href=\"$url\" class=\"nav-tab nav-tab-active\" aria-current=\"page\">" : "<a href=\"$url\" class=\"nav-tab\">"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The URL is escaped.
				echo esc_html( $link['title'] ) . '</a>';
			}
			echo '</nav>';
		}

		$option = $tab['option'];
		$stored = get_option( $option );
		foreach ( Forms::get_rows( $tab['fields'], \is_array( $stored ) ? $stored : array(), $option ) as $row ) {
			add_settings_field( $row['field']['id'] ?? '', esc_html( $row['field']['title'] ?? '' ), array( $this, 'render_field' ), $this->id, 'default', $row );
		}

		echo '<form action="options.php" method="post">';
		settings_fields( $option );
		echo '<table class="form-table" role="presentation">';
		do_settings_fields( $this->id, 'default' );
		echo '</table>';
		submit_button();
		echo '</form></div>';
	}

	/**
	 * Prints the control of a field row.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   FieldRow $args The field's row.
	 *
	 * @return  void
	 */
	public function render_field( array $args ): void {
		echo Forms::render_control( $args['field'], $args['value'], $args['name'], $args['id'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_control() escapes the control.
	}

	// endregion

	// region HELPERS

	/**
	 * Returns the page's definition, evaluating it and filtering its tabs on first use.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  Page
	 */
	protected function get_page(): array {
		if ( \is_null( $this->page ) ) {
			$page = $this->definition->get_options_page( $this->id );

			/**
			 * Filters the tabs of the options page, so that other plugins can add tabs or fields.
			 *
			 * @since   2.0.0
			 * @version 2.0.0
			 *
			 * @param   list<Tab> $tabs The page's tabs.
			 */
			$page['tabs'] = apply_filters( "{$this->id}_tabs", $page['tabs'] );
			$this->page   = $page;
		}

		return $this->page;
	}

	// endregion
}
