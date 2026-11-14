<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\AdminPage;

use DeepWebSolutions\Framework\Core\PluginIdentity;
use DeepWebSolutions\Framework\Settings\Forms;
use DeepWebSolutions\Framework\Shared\Exception\InvalidArgumentException;

\defined( 'ABSPATH' ) || exit;

/**
 * Adds a submenu page whose tabs edit their fields through the Settings API, each field stored in the option its id names.
 *
 * Each tab's form posts to options.php under the settings group {page id}_{tab slug}, so WordPress checks the nonce and the page's capability before the page sanitizes the submission. Because a field can name any option, which the page's capability then writes, a field id must start with the plugin's prefix.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @phpstan-import-type Field from Forms
 * @phpstan-import-type FieldRow from Forms
 * @phpstan-type        Tab array{slug: string, title: string}
 * @phpstan-type        Page array{parent: string, capability: string, page_title: string, menu_title: string, tabs: list<Tab>}
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

	/**
	 * The fields of each tab asked for, by the tab's slug, filtered and without the fields whose ids the page refuses.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     array<string, list<Field>>
	 */
	protected array $fields = array();

	/**
	 * The fields of the tab the current options.php request saves, by id, which register_settings() keeps for their sanitization.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     array<string, Field>
	 */
	protected array $posted = array();

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   PluginIdentity                 $identity   The plugin's identity, whose hook root starts the page's filters and whose prefix starts its field ids.
	 * @param   string                         $id         The page's menu slug.
	 * @param   OptionsPageDefinitionInterface $definition The page's definition.
	 *
	 * @throws  InvalidArgumentException Thrown when the id is not a valid key.
	 */
	public function __construct(
		protected PluginIdentity $identity,
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
	 * Registers, on the options.php request that saves one of the page's tabs, that tab's fields as settings of its group, and hooks the group's capability and each field's sanitization.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  void
	 */
	public function register_settings(): void {
		if ( 'options.php' !== ( $GLOBALS['pagenow'] ?? '' ) ) {
			return;
		}

		// WordPress core reads option_page from the request, not only the body, and trims it, so the same reading decides whether the submission is the page's.
		$group = \is_string( $_REQUEST['option_page'] ?? null ) ? sanitize_text_field( wp_unslash( $_REQUEST['option_page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- options.php verifies the nonce before it saves.
		$tab   = \array_find( $this->get_page()['tabs'], fn ( array $tab ): bool => $group === $this->get_group( $tab['slug'] ) );
		if ( \is_null( $tab ) ) {
			return;
		}

		foreach ( $this->get_fields( $tab['slug'] ) as $field ) {
			if ( isset( $field['id'] ) ) {
				$this->posted[ $field['id'] ] = $field;
				register_setting( $group, $field['id'], array( 'type' => \in_array( $field['type'], array( 'multiselect', 'checkboxes' ), true ) ? 'array' : 'string' ) );
				add_filter( "pre_update_option_{$field['id']}", array( $this, 'sanitize_submission' ), 10, 3 );
			}
		}
		add_filter( "option_page_capability_$group", array( $this, 'get_capability' ) );
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
	 * Returns the value to store for a field of the posted tab, or its stored value where the form posted nothing for it or the field rejects it.
	 *
	 * A rejection adds a settings error that names the field.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed  $value     The submitted value, unslashed by options.php, or null when the form posted none.
	 * @param   mixed  $old_value The stored value.
	 * @param   string $option    The option's name, which is the field's id.
	 *
	 * @return  mixed
	 */
	public function sanitize_submission( mixed $value, mixed $old_value, string $option ): mixed {
		$field = $this->posted[ $option ] ?? null;
		if ( \is_null( $field ) ) {
			return $value;
		}

		$result = Forms::sanitize_fields( array( $field ), \is_null( $value ) ? array() : array( $option => $value ) );
		if ( isset( $result['errors'][ $option ] ) ) {
			add_settings_error( $option, $option, esc_html( Forms::get_rejection_message( Forms::get_title( $field ), $result['errors'][ $option ] ) ) );
		}

		return \array_key_exists( $option, $result['values'] ) ? $result['values'][ $option ] : $old_value;
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

		$fields = $this->get_fields( $tab['slug'] );
		$ids    = \array_column( $fields, 'id' );
		wp_prime_option_caches( $ids );
		$stored = array();
		foreach ( $ids as $id ) {
			$value = get_option( $id );
			if ( false !== $value ) { // A field without a stored row shows its default.
				$stored[ $id ] = $value;
			}
		}

		$rows = Forms::get_rows( $fields, $stored, '' );
		if ( array() === $rows ) { // A tab without a field to save registers no settings group, so options.php would refuse its form.
			echo '</div>';
			return;
		}

		foreach ( $rows as $row ) {
			add_settings_field( $row['field']['id'], esc_html( $row['field']['title'] ), array( $this, 'render_field' ), $this->id, 'default', $row );
		}

		echo '<form action="options.php" method="post">';
		settings_fields( $this->get_group( $tab['slug'] ) );
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
			 * Filters the tabs of the options page, so that other plugins can add tabs, whose fields come through the page's fields filter.
			 *
			 * @since   2.0.0
			 * @version 2.0.0
			 *
			 * @param   list<Tab> $tabs    The page's tabs.
			 * @param   string    $page_id The page's menu slug.
			 */
			$page['tabs'] = apply_filters( "{$this->identity->hook_root}/options_page/tabs", $page['tabs'], $this->id );
			$this->page   = $page;
		}

		return $this->page;
	}

	/**
	 * Returns the fields of one of the page's tabs, evaluating and filtering them on first use, without a field whose id does not start with the plugin's prefix or is not a valid helper id, which it reports through _doing_it_wrong().
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $tab_slug The tab's slug.
	 *
	 * @return  list<Field>
	 */
	protected function get_fields( string $tab_slug ): array {
		if ( ! isset( $this->fields[ $tab_slug ] ) ) {
			/**
			 * Filters the fields of one tab of the options page, so that other plugins can add fields, whose ids start with the plugin's prefix.
			 *
			 * @since   2.0.0
			 * @version 2.0.0
			 *
			 * @param   list<Field> $fields   The tab's fields.
			 * @param   string      $tab_slug The tab's slug.
			 * @param   string      $page_id  The page's menu slug.
			 */
			$fields = apply_filters( "{$this->identity->hook_root}/options_page/fields", $this->definition->get_tab_fields( $this->id, $tab_slug ), $tab_slug, $this->id );

			$this->fields[ $tab_slug ] = array();
			foreach ( $fields as $field ) {
				$id = $field['id'] ?? null;
				try {
					if ( ! \is_null( $id ) ) {
						Forms::check_helper_id( $id );
					}
					$kept = \is_null( $id ) || \str_starts_with( $id, "{$this->identity->prefix}_" );
				} catch ( InvalidArgumentException ) {
					$kept = false;
				}

				if ( $kept ) {
					$this->fields[ $tab_slug ][] = $field;
				} else {
					_doing_it_wrong( __METHOD__, esc_html( "Options page '$this->id' leaves out field '$id', whose id is not '{$this->identity->prefix}_' followed by lowercase letters, digits, underscores and hyphens." ), '2.0.0' );
				}
			}
		}

		return $this->fields[ $tab_slug ];
	}

	/**
	 * Returns the settings group of one of the page's tabs, which names its form's nonce and capability filter.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $tab_slug The tab's slug.
	 *
	 * @return  string
	 */
	protected function get_group( string $tab_slug ): string {
		return "{$this->id}_$tab_slug";
	}

	// endregion
}
