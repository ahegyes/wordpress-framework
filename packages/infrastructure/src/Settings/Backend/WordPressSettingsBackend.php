<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Backend;

use DeepWebSolutions\Framework\Settings\Schema\Exceptions\DuplicateSettingsFieldException;
use DeepWebSolutions\Framework\Settings\Schema\Exceptions\DuplicateSettingsSectionException;
use DeepWebSolutions\Framework\Settings\Schema\Exceptions\InvalidSettingsFieldException;
use DeepWebSolutions\Framework\Settings\Schema\Exceptions\UnsupportedRestExposureException;
use DeepWebSolutions\Framework\Settings\Schema\Field\FieldProcessor;
use DeepWebSolutions\Framework\Settings\Schema\Field\FieldRenderer;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsField;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsPage;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsSection;
use DeepWebSolutions\Framework\Shared\Result\Failure;
use DeepWebSolutions\Framework\Storage\OptionsStore;
use Psr\Log\LoggerInterface;

use function DeepWebSolutions\Framework\Settings\Schema\assert_unique_section_and_field_ids;
use function DeepWebSolutions\Framework\Settings\Schema\field_label_html;
use function DeepWebSolutions\Framework\Settings\Schema\is_field_editable_by_current_user;
use function DeepWebSolutions\Framework\Settings\Schema\resolve_field_control_id;
use function DeepWebSolutions\Framework\Settings\Schema\rest_schema_for_field;
use function DeepWebSolutions\Framework\Settings\Schema\wordpress_field_type_sanitizers;

/**
 * WordPress options-backed settings backend for a single page.
 *
 * Registers the page as an admin submenu and one grouped option per section via
 * the native Settings API, and persists each section's fields in that section's
 * own wp_options row. Field ids are page-unique, so it routes get/set/has/delete
 * to the declaring section by id.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class WordPressSettingsBackend implements SettingsBackendInterface {
	// region FIELDS AND CONSTANTS

	/**
	 * The capability the WordPress settings REST endpoint enforces for every read and write.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	protected const REST_CAPABILITY = 'manage_options';

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
	 * Map of section id to whether any of its fields opts into autoloading the section option.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     array<string, bool>
	 */
	protected array $section_autoload = array();

	/**
	 * Map of section id to the REST schema for its option, for each section whose fields all opt into REST.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     array<string, array<string, mixed>>
	 */
	protected array $section_rest_schemas = array();

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

	/**
	 * Whether the page's settings are registered. An admin request that boots the REST server fires both
	 * registration hooks; the guard keeps the option filters from being added twice.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     bool
	 */
	protected bool $settings_registered = false;

	/**
	 * Processor that sanitizes and validates submitted values.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     FieldProcessor
	 */
	protected FieldProcessor $processor;

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
	 * @param   ?FieldProcessor  $processor Processor for submitted values; null applies one carrying the per-type default sanitizers.
	 */
	public function __construct(
		protected ?LoggerInterface $logger = null,
		protected FieldRenderer $renderer = new FieldRenderer(),
		?FieldProcessor $processor = null,
	) {
		$this->processor = $processor ?? new FieldProcessor( type_sanitizers: wordpress_field_type_sanitizers() );
	}

	// endregion

	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @throws  DuplicateSettingsSectionException If two sections on the page share an id.
	 * @throws  DuplicateSettingsFieldException If two fields on the page share an id.
	 * @throws  UnsupportedRestExposureException If a section's REST exposure cannot be represented.
	 */
	#[\Override]
	public function register_page( SettingsPage $page ): void {
		$this->page                 = $page;
		$this->field_section        = $this->map_fields( $page );
		$this->section_autoload     = $this->map_section_autoload( $page );
		$this->section_rest_schemas = $this->map_section_rest_schemas( $page );

		if ( \did_action( 'admin_menu' ) > 0 ) {
			$this->logger?->warning(
				'Settings page registered after admin_menu fired; its menu will not appear.',
				array( 'slug' => $page->slug ),
			);
		}

		\add_action( 'admin_menu', array( $this, 'add_menu' ) );
		\add_action( 'admin_init', array( $this, 'register_settings' ) );
		\add_action( 'rest_api_init', array( $this, 'register_settings' ) );
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

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function option_keys( SettingsPage $page ): array {
		$keys = array();
		foreach ( $page->sections as $section ) {
			$keys[] = $page->slug . '-' . $section->id;
		}

		return $keys;
	}

	// endregion

	// region HOOKS

	/**
	 * Registers the page's admin submenu. Hooked to admin_menu; no-op until register_page() runs.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function add_menu(): void {
		$page = $this->page;
		if ( null === $page ) {
			return;
		}

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
	 * Registers one setting, capability filter, and autoload-policy filter per section. Hooked to both
	 * admin_init and rest_api_init; runs once per request, on whichever fires first.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function register_settings(): void {
		$page = $this->page;
		if ( null === $page || $this->settings_registered ) {
			return;
		}
		$this->settings_registered = true;

		foreach ( $page->sections as $section ) {
			$option_name = $page->slug . '-' . $section->id;
			$rest_schema = $this->section_rest_schemas[ $section->id ] ?? null;
			$autoload    = $this->section_autoload[ $section->id ] ?? false;

			$args = array(
				// A REST-exposed section is an object keyed by field id; a plain section is an opaque map.
				'type'              => null !== $rest_schema ? 'object' : 'array',
				'sanitize_callback' => fn ( mixed $input ): mixed => $this->sanitize( $section, $option_name, $input ),
				'default'           => array(),
			);
			if ( null !== $rest_schema ) {
				$args['show_in_rest'] = array( 'schema' => $rest_schema );
			}

			\register_setting( $option_name, $option_name, $args );
			\add_filter( "option_page_capability_{$option_name}", fn () => $page->capability );

			// The form save calls update_option with no autoload argument, so WP would resolve the option's
			// autoload by size rather than the section policy; pin the policy so both write paths agree.
			\add_filter(
				'wp_default_autoload_value',
				static fn ( ?bool $default_value, string $option ): ?bool => $option === $option_name ? $autoload : $default_value,
				10,
				2,
			);
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

		return $this->stores[ $section_id ] ??= new OptionsStore(
			$this->page->slug . '-' . $section_id,
			autoload: $this->section_autoload[ $section_id ] ?? false,
		);
	}

	/**
	 * Builds the section-id to autoload-policy map: a section autoloads its option when any field opts in.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   SettingsPage $page Page whose sections to map.
	 *
	 * @return  array<string, bool>
	 */
	protected function map_section_autoload( SettingsPage $page ): array {
		$map = array();
		foreach ( $page->sections as $section ) {
			$autoload = false;
			foreach ( $section->fields as $field ) {
				if ( $field->autoload ) {
					$autoload = true;
					break;
				}
			}
			$map[ $section->id ] = $autoload;
		}

		return $map;
	}

	/**
	 * Builds the section-id to REST-schema map: a section opted into REST is exposed as one object setting.
	 *
	 * A section persists all its fields in one option row, so REST exposure is whole-row: the row's value is
	 * an object keyed by field id. The WordPress settings endpoint gates every read and write of that row by
	 * one fixed capability, so a section is exposed only when the page requires exactly that capability, every
	 * field opts in, every field is a built-in type, and no field narrows access with its own capability — any
	 * finer or different access intent could not be honored. A section that cannot meet this is rejected at
	 * registration rather than silently nulling the read or exposing a field below its intended access. A
	 * stored value outside the generated schema — a programmatic null write, or a key left by a removed field
	 * — nulls the section's REST read until the next form save rewrites the row.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   SettingsPage $page Page whose sections to map.
	 *
	 * @throws  UnsupportedRestExposureException If a section opts in only some of its fields, exposes a non-built-in field type, or its access intent is not the endpoint's fixed capability.
	 *
	 * @return  array<string, array<string, mixed>>
	 */
	protected function map_section_rest_schemas( SettingsPage $page ): array {
		$map = array();
		foreach ( $page->sections as $section ) {
			$properties = array();
			foreach ( $section->fields as $field ) {
				if ( ! $field->show_in_rest ) {
					continue;
				}
				if ( null !== $field->capability ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- framework-internal exception; never reaches an HTML output context unescaped.
					throw new UnsupportedRestExposureException( "Settings field '$field->id' narrows access with its own capability, which the REST settings endpoint's fixed '" . self::REST_CAPABILITY . "' gate cannot honor." );
				}
				$properties[ $field->id ] = rest_schema_for_field( $field );
			}

			if ( array() === $properties ) {
				continue;
			}

			if ( self::REST_CAPABILITY !== $page->capability ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- framework-internal exception; never reaches an HTML output context unescaped.
				throw new UnsupportedRestExposureException( "Settings page '$page->slug' requires '$page->capability', but the REST settings endpoint gates every section by '" . self::REST_CAPABILITY . "', so its sections cannot be exposed via REST." );
			}

			if ( \count( $properties ) !== \count( $section->fields ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- framework-internal exception; never reaches an HTML output context unescaped.
				throw new UnsupportedRestExposureException( "Settings section '$section->id' exposes only some of its fields via REST; section-grouped storage exposes a section entirely or not at all." );
			}

			$map[ $section->id ] = array(
				'type'                 => 'object',
				'properties'           => $properties,
				'additionalProperties' => false,
			);
		}

		return $map;
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

		if ( $this->renders_settings_errors( $page ) ) {
			\settings_errors( 'general' );
			foreach ( $page->sections as $section ) {
				\settings_errors( $page->slug . '-' . $section->id );
			}
		}

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
				$control_name = $option_name . '[' . $field->id . ']';
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- field_label_html returns markup escaped at each interpolation point.
				echo '<tr><th scope="row">' . field_label_html( $field, resolve_field_control_id( $field, $control_name ) ) . '</th><td>';
				$this->render_field( $control_name, $field, $stored );
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
	 * @param   string               $control_name HTML name the control submits under.
	 * @param   SettingsField        $field        Field to render.
	 * @param   array<string, mixed> $stored       Section option, read once by the caller.
	 */
	protected function render_field( string $control_name, SettingsField $field, array $stored ): void {
		$value = \array_key_exists( $field->id, $stored ) ? $stored[ $field->id ] : $field->default_value;

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- FieldRenderer returns markup already escaped at each interpolation point.
		echo $this->renderer->render( $field, $value, $control_name );
	}

	/**
	 * Whether the page render owns Settings API notices for this page.
	 *
	 * The options-general parent loads WordPress' options-head.php, which renders Settings API notices
	 * before the page callback. Custom parent locations do not, so the backend renders them once at the
	 * top of the page: WordPress' shared 'general' bucket (options.php registers the save confirmation
	 * under that slug) plus each section's own bucket. The per-bucket filter keeps a page whose
	 * capability is weaker than manage_options from echoing unrelated settings' messages out of the
	 * shared Settings API transient. A page kept under options-general.php gets WordPress' own
	 * unfiltered render (options-head.php) ahead of this callback — pair that location with an
	 * admin-tier capability.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   SettingsPage $page Page being rendered.
	 *
	 * @return  bool
	 */
	protected function renders_settings_errors( SettingsPage $page ): bool {
		return 'options-general.php' !== ( $page->location ?? 'options-general.php' ) && \function_exists( 'settings_errors' );
	}

	/**
	 * Sanitizes a submitted section payload through the field processor.
	 *
	 * The sanitize_option filter fires on every update_option for the section, including a programmatic
	 * set() or delete() this backend performs. Those are flagged and pass through untouched, so only a
	 * write this backend did not initiate — the options.php form save or a REST write — is processed and
	 * coerced. On such a save, a field whose capability the current user lacks keeps its stored value, so a
	 * user holding only the page capability cannot change a more privileged field. A field whose submission
	 * is rejected likewise keeps its stored value and reports the rejection, rather than overwriting a valid
	 * setting with an empty one. The whole section is processed each time, so a field the submission omits is
	 * cleared to its empty: both the form (which posts the whole section) and a REST write replace the row,
	 * matching the settings endpoint's replace-on-write semantics, so a REST client sends the complete section.
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

			$result = $this->processor->process_or_reject( $field, $values );
			if ( $result instanceof Failure ) {
				// A rejected submission keeps the field's prior value, never overwriting it with an empty.
				if ( \array_key_exists( $field->id, $existing ) ) {
					$output[ $field->id ] = $existing[ $field->id ];
				}
				// add_settings_error lives in the admin includes; surface the rejection where the settings
				// page renders it, but never fatal a save that runs outside the admin.
				if ( \function_exists( 'add_settings_error' ) ) {
					\add_settings_error(
						$option_name,
						$field->id,
						\sprintf(
							/* translators: %s: settings field label. */
							\esc_html__( 'The value for “%s” was invalid and was not saved; the previous value was kept.', 'wp-framework-infrastructure' ),
							\esc_html( $field->label ),
						),
					);
				}
				continue;
			}

			$output[ $field->id ] = $result->value;
		}

		return $output;
	}

	// endregion
}
