<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Backend;

use DeepWebSolutions\Framework\Settings\Schema\Exceptions\DuplicateSettingsFieldException;
use DeepWebSolutions\Framework\Settings\Schema\Exceptions\DuplicateSettingsSectionException;
use DeepWebSolutions\Framework\Settings\Schema\Exceptions\InvalidSettingsFieldException;
use DeepWebSolutions\Framework\Settings\Schema\FieldProcessor;
use DeepWebSolutions\Framework\Settings\Schema\FieldRenderer;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsField;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsPage;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsSection;
use DeepWebSolutions\Framework\Storage\OptionsStore;
use Psr\Log\LoggerInterface;

use function DeepWebSolutions\Framework\Settings\Schema\assert_unique_section_and_field_ids;
use function DeepWebSolutions\Framework\Settings\Schema\is_field_editable_by_current_user;

/**
 * WordPress options-backed settings backend for a single page.
 *
 * Registers the page as an admin submenu and one grouped option per section via
 * the native Settings API, and persists each section's fields in that section's
 * own wp_options row (not autoloaded). Field ids are page-unique, so it routes
 * get/set/has/delete to the declaring section by id.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class WordPressSettingsBackend implements SettingsBackendInterface {
	// region FIELDS AND CONSTANTS

	/**
	 * The registered page; null until register_page() runs.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     ?SettingsPage
	 */
	protected ?SettingsPage $page = null;

	/**
	 * Map of field id to the id of the section that declares it.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     array<string, string>
	 */
	protected array $field_section = array();

	/**
	 * Per-section option-store cache, keyed by section id.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     array<string, OptionsStore<mixed>>
	 */
	protected array $stores = array();

	/**
	 * Depth of in-progress programmatic writes; the backend's own writes, which may nest, bypass the form sanitizer.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     int
	 */
	protected int $writing = 0;

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   ?LoggerInterface $logger    Logger for late-registration diagnostics; null silences them.
	 * @param   FieldRenderer    $renderer  Renderer for the page's field controls.
	 * @param   FieldProcessor   $processor Processor for sanitizing submitted values.
	 */
	public function __construct(
		protected ?LoggerInterface $logger = null,
		protected FieldRenderer $renderer = new FieldRenderer(),
		protected FieldProcessor $processor = new FieldProcessor(),
	) {}

	// endregion

	// region METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @throws  DuplicateSettingsSectionException If two sections on the page share an id.
	 * @throws  DuplicateSettingsFieldException If two fields on the page share an id.
	 */
	#[\Override]
	public function register_page( SettingsPage $page ): void {
		$this->page          = $page;
		$this->field_section = $this->map_fields( $page );

		if ( \did_action( 'admin_menu' ) > 0 ) {
			$this->logger?->warning(
				'Settings page registered after admin_menu fired; its menu will not appear.',
				array( 'slug' => $page->slug ),
			);
		}

		\add_action( 'admin_menu', fn () => $this->add_menu( $page ) );
		\add_action( 'admin_init', fn () => $this->register_settings( $page ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function get( string $field_id, mixed $default_value = null ): mixed {
		return $this->store_for( $field_id )->get( $field_id, $default_value );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function set( string $field_id, mixed $value ): void {
		++$this->writing;
		try {
			$this->store_for( $field_id )->set( $field_id, $value );
		} finally {
			--$this->writing;
		}
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function has( string $field_id ): bool {
		return $this->store_for( $field_id )->has( $field_id );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function delete( string $field_id ): bool {
		++$this->writing;
		try {
			return $this->store_for( $field_id )->delete( $field_id );
		} finally {
			--$this->writing;
		}
	}

	// endregion

	// region HELPERS

	/**
	 * Builds the field-id to section-id map, rejecting a page-duplicate field id.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   SettingsPage $page Page whose fields to map.
	 *
	 * @throws  DuplicateSettingsSectionException If two sections on the page share an id.
	 * @throws  DuplicateSettingsFieldException If two fields on the page share an id.
	 *
	 * @return  array<string, string>
	 */
	protected function map_fields( SettingsPage $page ): array {
		assert_unique_section_and_field_ids( $page );

		$map = array();
		foreach ( $page->sections as $section ) {
			foreach ( $section->fields as $field ) {
				$map[ $field->id ] = $section->id;
			}
		}

		return $map;
	}

	/**
	 * Resolves the option store for a field's section, creating it once per section.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $field_id Field to resolve a store for.
	 *
	 * @throws  InvalidSettingsFieldException If the field is not registered on this page.
	 *
	 * @return  OptionsStore<mixed>
	 */
	protected function store_for( string $field_id ): OptionsStore {
		if ( null === $this->page || ! \array_key_exists( $field_id, $this->field_section ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- framework-internal exception; never reaches an HTML output context unescaped.
			throw new InvalidSettingsFieldException( "Settings field '$field_id' is not registered on this page." );
		}

		$section_id = $this->field_section[ $field_id ];

		return $this->stores[ $section_id ] ??= new OptionsStore( $this->page->slug . '-' . $section_id, autoload: false );
	}

	/**
	 * Registers the page's admin submenu. Hooked to admin_menu.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   SettingsPage $page Page to add.
	 */
	protected function add_menu( SettingsPage $page ): void {
		// WordPress only wptexturizes the submenu menu_title before printing it (page_title is strip_tagged
		// for the document title and escaped in render_page), so the menu title is escaped here.
		\add_submenu_page(
			$page->location ?? 'options-general.php',
			$page->page_title,
			\esc_html( $page->menu_title ),
			$page->capability,
			$page->slug,
			fn () => $this->render_page( $page ),
		);
	}

	/**
	 * Registers one setting and capability filter per section. Hooked to admin_init.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   SettingsPage $page Page whose sections to register.
	 */
	protected function register_settings( SettingsPage $page ): void {
		foreach ( $page->sections as $section ) {
			$option_name = $page->slug . '-' . $section->id;

			\register_setting(
				$option_name,
				$option_name,
				array(
					'type'              => 'array',
					'sanitize_callback' => fn ( mixed $input ): mixed => $this->sanitize( $section, $option_name, $input ),
					'default'           => array(),
				),
			);
			\add_filter( "option_page_capability_{$option_name}", fn () => $page->capability );
		}
	}

	/**
	 * Renders the settings page: one save form per section.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   SettingsPage $page Page to render.
	 */
	protected function render_page( SettingsPage $page ): void {
		echo '<div class="wrap"><h1>' . \esc_html( $page->page_title ) . '</h1>';

		foreach ( $page->sections as $section ) {
			$option_name = $page->slug . '-' . $section->id;
			$stored      = \get_option( $option_name, array() );
			$stored      = \is_array( $stored ) ? $stored : array();

			echo '<h2>' . \esc_html( $section->title ) . '</h2>';
			echo '<form action="options.php" method="post">';
			\settings_fields( $option_name );
			echo '<table class="form-table" role="presentation"><tbody>';
			foreach ( $section->fields as $field ) {
				if ( ! is_field_editable_by_current_user( $field ) ) {
					continue;
				}
				echo '<tr><th scope="row">' . \esc_html( $field->label ) . '</th><td>';
				$this->render_field( $option_name, $field, $stored );
				echo '</td></tr>';
			}
			echo '</tbody></table>';
			\submit_button();
			echo '</form>';
		}

		echo '</div>';
	}

	/**
	 * Renders one field control, binding its stored value.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string               $option_name Section option the field persists into.
	 * @param   SettingsField        $field       Field to render.
	 * @param   array<string, mixed> $stored      Section option, read once by the caller.
	 */
	protected function render_field( string $option_name, SettingsField $field, array $stored ): void {
		$value = \array_key_exists( $field->id, $stored ) ? $stored[ $field->id ] : $field->default_value;

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- FieldRenderer returns markup already escaped at each interpolation point.
		echo $this->renderer->render( $field, $value, $option_name . '[' . $field->id . ']' );
	}

	/**
	 * Sanitizes a submitted section payload through the field processor.
	 *
	 * The sanitize_option filter fires on every update_option for the section, including a programmatic
	 * set() or delete() this backend performs. Those are flagged and pass through untouched, so only a
	 * write this backend did not initiate — the options.php form save — is processed and coerced. On a
	 * form save, a field whose capability the current user lacks keeps its stored value, so a user
	 * holding only the page capability cannot change a more privileged field.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   SettingsSection $section     Section whose fields define the processing.
	 * @param   string          $option_name Section option, read to preserve fields the user cannot edit.
	 * @param   mixed           $input       Raw submitted values for the section.
	 *
	 * @return  mixed
	 */
	protected function sanitize( SettingsSection $section, string $option_name, mixed $input ): mixed {
		if ( $this->writing > 0 ) {
			return $input;
		}

		$values   = \is_array( $input ) ? $input : array();
		$existing = \get_option( $option_name, array() );
		$existing = \is_array( $existing ) ? $existing : array();

		$output = array();
		foreach ( $section->fields as $field ) {
			if ( ! is_field_editable_by_current_user( $field ) ) {
				if ( \array_key_exists( $field->id, $existing ) ) {
					$output[ $field->id ] = $existing[ $field->id ];
				}
				continue;
			}
			$output[ $field->id ] = $this->processor->process( $field, $values );
		}

		return $output;
	}

	// endregion
}
