<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\WooCommerce\Setting;

use Automattic\WooCommerce\Admin\Settings\SettingsSectionRegistry;
use DeepWebSolutions\Framework\Shared\Exception\InvalidArgumentException;
use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesStorageTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\ProvidesHelperIdsTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\RunsAdminRequestsTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\SerializesFormsTrait;
use DeepWebSolutions\Framework\WooCommerce\Setting\Fields;
use DeepWebSolutions\Framework\WooCommerce\Setting\SettingsSection;
use DeepWebSolutions\Framework\WooCommerce\Setting\SettingsTab;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( SettingsTab::class )]
#[CoversClass( SettingsSection::class )]
#[CoversClass( Fields::class )]
final class SettingsTest extends TestCase {
	use CreatesUsersTrait;
	use IsolatesHooksTrait;
	use IsolatesStorageTrait;
	use ProvidesHelperIdsTrait;
	use RunsAdminRequestsTrait;
	use SerializesFormsTrait;

	protected const string FIXTURE = __DIR__ . '/../../../Fixtures/WooCommerce/acme-settings.php';

	protected const array COLORS = array(
		'red'  => 'Red',
		'blue' => 'Blue',
		'teal' => 'Teal',
	);

	protected int $admin;

	#[\Override]
	public static function setUpBeforeClass(): void {
		require_once \WC_ABSPATH . 'includes/admin/settings/class-wc-settings-page.php';
	}

	#[\Override]
	protected function setUp(): void {
		$this->admin = $this->make_user( 'acme_wc_settings_admin', 'administrator' );
	}

	#[\Override]
	protected function tearDown(): void {
		SettingsSectionRegistry::get_instance()->unregister_all();
		$this->reset_settings_pages();
		$GLOBALS['wp_rest_server'] = null;
		\wp_set_current_user( 0 );
	}

	public static function silent_and_worded_rejections(): array {
		return array(
			'worded' => array( 'acme:reject', "Customer's <b>note</b> was not saved, so it keeps its previous value. Use at most four characters." ),
			'silent' => array( 'acme:reject-silently', "Customer's <b>note</b> was not saved because its value is not valid, so it keeps its previous value." ),
		);
	}

	#[DataProvider( 'invalid_helper_ids' )]
	public function test_an_invalid_tab_id_is_rejected( string $id ): void {
		$this->expectException( InvalidArgumentException::class );

		new SettingsTab( $id, $this->tab_definition( array() ) );
	}

	#[DataProvider( 'invalid_helper_ids' )]
	public function test_an_invalid_section_id_is_rejected( string $id ): void {
		$this->expectException( InvalidArgumentException::class );

		new SettingsSection( $id, $this->section_definition( 'products', array() ) );
	}

	#[DataProvider( 'v1_helper_ids' )]
	public function test_every_v1_key_is_a_valid_tab_and_section_id( string $id ): void {
		self::assertSame( $id, ( new SettingsTab( $id, $this->tab_definition( array() ) ) )->get_id() );
		self::assertSame( $id, ( new SettingsSection( $id, $this->section_definition( 'products', array() ) ) )->get_id() );
	}

	public function test_a_section_builds_its_fields_only_when_woocommerce_asks_for_them(): void {
		$calls    = array();
		$sections = array();
		$labels   = array(
			''         => 'General',
			'advanced' => 'Advanced',
		);
		foreach ( $labels as $section_id => $label ) {
			$sections[ $section_id ] = array(
				'label'  => $label,
				'fields' => static function () use ( &$calls, $section_id ): array {
					$calls[] = $section_id;

					return array();
				},
			);
		}
		$tab = new SettingsTab( $this->prefix, $this->tab_definition( $sections ) );
		self::assertSame( array(), $calls );

		$tab->get_settings_for_section( 'advanced' );
		$tab->get_settings_for_section( 'advanced' );

		self::assertSame( array( 'advanced' ), $calls );
		self::assertSame(
			array(
				''         => 'General',
				'advanced' => 'Advanced',
			),
			$tab->get_sections()
		);
	}

	public function test_a_tab_lists_its_sections_and_renders_only_the_current_sections_fields(): void {
		$spec = $this->tab_spec(
			array(
				''         => self::section( 'General', array( $this->field( 'text', 'name' ) ) ),
				'advanced' => self::section( 'Advanced', array( $this->field( 'checkbox', 'flag' ) ) ),
			)
		);

		$general  = self::document( $this->open( $spec )['body'] );
		$advanced = self::document( $this->open( $spec, 'advanced' )['body'] );

		self::assertSame( array( 'General', 'Advanced' ), self::texts( $general, 'ul.subsubsub a' ) );
		self::assertNotNull( $general->querySelector( "form#mainform [name=\"{$this->prefix}_name\"]" ) );
		self::assertNull( $general->querySelector( "form#mainform [name=\"{$this->prefix}_flag\"]" ) );
		self::assertNotNull( $advanced->querySelector( "form#mainform [name=\"{$this->prefix}_flag\"]" ) );
		self::assertNull( $advanced->querySelector( "form#mainform [name=\"{$this->prefix}_name\"]" ) );
	}

	public function test_saving_a_section_stores_its_values_and_leaves_the_other_section_untouched(): void {
		\update_option( "{$this->prefix}_name", 'old' );
		\update_option( "{$this->prefix}_flag", 'yes' );
		$spec = $this->tab_spec(
			array(
				''         => self::section( 'General', array( $this->field( 'text', 'name' ) ) ),
				'advanced' => self::section( 'Advanced', array( $this->field( 'checkbox', 'flag' ) ) ),
			)
		);

		$this->save( $spec, 'advanced', array( "{$this->prefix}_flag" => false ) );
		self::assertSame( 'no', \get_option( "{$this->prefix}_flag" ) );
		self::assertSame( 'old', \get_option( "{$this->prefix}_name" ) );

		$this->save( $spec, '', array( "{$this->prefix}_name" => 'new' ) );
		self::assertSame( 'new', \get_option( "{$this->prefix}_name" ) );
		self::assertSame( 'no', \get_option( "{$this->prefix}_flag" ) );
	}

	public function test_a_tab_field_round_trips_through_woocommerces_rest_api(): void {
		$this->add_tab_in_process( array( '' => $this->in_process_section( array( $this->field( 'text', 'name' ) ) ) ) );

		$updated = $this->rest( 'PUT', "/wc/v3/settings/{$this->prefix}/{$this->prefix}_name", array( 'value' => 'via rest' ) );
		$listed  = $this->rest( 'GET', "/wc/v3/settings/{$this->prefix}" );

		self::assertSame( 200, $updated->get_status() );
		self::assertSame( 'via rest', \get_option( "{$this->prefix}_name" ) );
		self::assertSame( array( 'via rest' ), \array_column( \array_filter( (array) $listed->get_data(), fn ( array $setting ): bool => "{$this->prefix}_name" === $setting['id'] ), 'value' ) );
	}

	public function test_a_registered_section_renders_under_its_parent_tab_and_saves_its_fields(): void {
		$spec = array(
			'section' => array(
				'id'     => $this->prefix,
				'tab'    => 'products',
				'label'  => 'Acme',
				'fields' => array( $this->field( 'text', 'name', array( 'validate' => 'acme:append' ) ) ),
			),
		);

		$products = self::document( $this->open( $spec, '', 'products' )['body'] );
		$saved    = $this->save( $spec, $this->prefix, array( "{$this->prefix}_name" => 'v' ), array(), 'products' );

		self::assertContains( 'Acme', self::texts( $products, 'ul.subsubsub a' ) );
		self::assertSame( 'v!', \get_option( "{$this->prefix}_name" ) );
		self::assertSame( array( array( 'validate', "{$this->prefix}_name", 'v' ) ), $saved['records'] );
	}

	public function test_a_section_whose_id_is_taken_is_rejected_by_the_registry(): void {
		$wrong    = array();
		$registry = SettingsSectionRegistry::get_instance();
		\add_filter( 'doing_it_wrong_trigger_error', '__return_false' );
		\add_action(
			'doing_it_wrong_run',
			static function ( string $function_name ) use ( &$wrong ): void {
				$wrong[] = $function_name;
			}
		);

		self::assertTrue( $registry->register( new SettingsSection( $this->prefix, $this->section_definition( 'products', array() ) ) ) );
		self::assertFalse( $registry->register( new SettingsSection( $this->prefix, $this->section_definition( 'products', array() ) ) ) );
		self::assertSame( array( SettingsSectionRegistry::class . '::register' ), $wrong );
	}

	public function test_a_disabled_field_is_never_saved_even_when_its_value_is_posted(): void {
		\update_option( "{$this->prefix}_name", 'old' );
		$spec = $this->tab_spec( array( '' => self::section( 'General', array( $this->field( 'text', 'name', array( 'custom_attributes' => array( 'disabled' => 'disabled' ) ) ) ) ) ) );

		$this->save( $spec, '', array(), array( "{$this->prefix}_name" => 'tampered' ) );

		self::assertSame( 'old', \get_option( "{$this->prefix}_name" ) );
	}

	public function test_a_field_without_validate_stores_woocommerces_value(): void {
		$spec = $this->tab_spec( array( '' => self::section( 'General', array( $this->field( 'text', 'name' ) ) ) ) );

		$this->save( $spec, '', array( "{$this->prefix}_name" => ' <b>bold</b> ' ) );

		self::assertSame( 'bold', \get_option( "{$this->prefix}_name" ) );
	}

	public function test_validate_receives_the_raw_submission_alone_and_its_return_value_is_stored(): void {
		$spec = $this->tab_spec( array( '' => self::section( 'General', array( $this->field( 'text', 'name', array( 'validate' => 'acme:uppercase' ) ) ) ) ) );

		$saved = $this->save( $spec, '', array( "{$this->prefix}_name" => ' abc ' ) );

		self::assertSame( ' ABC ', \get_option( "{$this->prefix}_name" ) );
		self::assertSame( array( array( 'validate', "{$this->prefix}_name", ' abc ' ) ), $saved['records'] );
	}

	#[DataProvider( 'silent_and_worded_rejections' )]
	public function test_a_rejection_keeps_the_stored_value_and_shows_its_message_once_as_text( string $behavior, string $message ): void {
		\update_option( "{$this->prefix}_name", 'old' );
		$spec = $this->tab_spec(
			array(
				'' => self::section(
					'General',
					array(
						$this->field(
							'text',
							'name',
							array(
								'title'    => "Customer's <b>note</b>",
								'validate' => $behavior,
							)
						),
					)
				),
			)
		);

		$saved = self::document( $this->save( $spec, '', array( "{$this->prefix}_name" => 'too long' ) )['body'] );

		self::assertSame( 'old', \get_option( "{$this->prefix}_name" ) );
		self::assertSame( array( $message ), self::texts( $saved, '#message.error' ) );
		self::assertNull( $saved->querySelector( '#message.error b' ) );
	}

	// An absent textarea would join them, but WooCommerce's own save raises a deprecation for it, passing null to trim(), before the bridge runs.
	public static function unposted_fields(): array {
		return array(
			'select' => array( 'select', array( 'options' => self::COLORS ), 'blue' ),
			'text'   => array( 'text', array(), 'old' ),
		);
	}

	public static function cleared_woocommerce_lists(): array {
		return array(
			'multiselect'            => array( 'multiselect', array( 'options' => self::COLORS ), array( 'red' ) ),
			'multi-select-countries' => array( 'multi_select_countries', array(), array( 'RO' ) ),
		);
	}

	#[DataProvider( 'unposted_fields' )]
	public function test_a_field_the_form_did_not_post_keeps_its_stored_value( string $type, array $keys, string $stored ): void {
		\update_option( "{$this->prefix}_field", $stored );
		$spec = $this->tab_spec( array( '' => self::section( 'General', array( $this->field( $type, 'field', $keys ) ) ) ) );
		$data = self::serialize_form( $this->open( $spec )['body'], 'form#mainform' ) + array( 'save' => 'Save changes' );
		unset( $data[ "{$this->prefix}_field" ] );

		$this->admin_request( 'POST', $this->settings_path( $this->prefix, '' ), $this->admin, self::FIXTURE, $spec, $data );

		self::assertSame( $stored, \get_option( "{$this->prefix}_field" ) );
	}

	#[DataProvider( 'cleared_woocommerce_lists' )]
	public function test_a_cleared_woocommerce_list_without_validate_stores_an_empty_list( string $type, array $keys, array $stored ): void {
		\update_option( "{$this->prefix}_list", $stored );
		$spec = $this->tab_spec( array( '' => self::section( 'General', array( $this->field( $type, 'list', $keys ) ) ) ) );

		$this->save( $spec, '', array( "{$this->prefix}_list" => array() ) );

		self::assertSame( array(), \get_option( "{$this->prefix}_list" ) );
	}

	public function test_validate_receives_no_for_an_unchecked_checkbox_and_an_empty_list_for_a_cleared_multiselect(): void {
		\update_option( "{$this->prefix}_flag", 'yes' );
		\update_option( "{$this->prefix}_colors", array( 'red' ) );
		$spec = $this->tab_spec(
			array(
				'' => self::section(
					'General',
					array(
						$this->field( 'checkbox', 'flag', array( 'validate' => 'acme:record' ) ),
						$this->field(
							'multiselect',
							'colors',
							array(
								'options'  => self::COLORS,
								'validate' => 'acme:record',
							)
						),
					)
				),
			)
		);

		$saved = $this->save(
			$spec,
			'',
			array(
				"{$this->prefix}_flag"   => false,
				"{$this->prefix}_colors" => array(),
			)
		);

		self::assertSame(
			array(
				array( 'validate', "{$this->prefix}_flag", 'no' ),
				array( 'validate', "{$this->prefix}_colors", array() ),
			),
			$saved['records']
		);
		self::assertSame( 'no', \get_option( "{$this->prefix}_flag" ) );
		self::assertSame( array(), \get_option( "{$this->prefix}_colors" ) );
	}

	public function test_over_rest_validate_receives_woocommerces_normalized_value(): void {
		$received = array();
		$this->add_tab_in_process(
			array(
				'' => $this->in_process_section(
					array(
						$this->field(
							'text',
							'name',
							array(
								'validate' => static function ( mixed $raw ) use ( &$received ): mixed {
									$received[] = $raw;

									return $raw;
								},
							)
						),
					)
				),
			)
		);

		$this->rest( 'PUT', "/wc/v3/settings/{$this->prefix}/{$this->prefix}_name", array( 'value' => ' <b>bold</b><script>x</script> ' ) );

		self::assertSame( array( '<b>bold</b>x' ), $received );
		self::assertSame( '<b>bold</b>x', \get_option( "{$this->prefix}_name" ) );
	}

	public function test_an_option_the_helpers_do_not_own_passes_through_unchanged(): void {
		$tab = $this->add_tab_in_process( array( '' => $this->in_process_section( array( $this->field( 'text', 'name', array( 'validate' => static fn (): \WP_Error => new \WP_Error( 'acme' ) ) ) ) ) ) );
		$tab->get_settings_for_section( '' );

		$foreign = array(
			'id'   => "{$this->prefix}_foreign",
			'type' => 'text',
		);

		self::assertSame( 'kept', \apply_filters( 'woocommerce_admin_settings_sanitize_option', 'kept', $foreign, 'raw' ) );
	}

	public function test_with_a_section_registered_on_a_tab_each_fields_validate_runs_once_per_save(): void {
		$spec = $this->tab_spec( array( '' => self::section( 'General', array( $this->field( 'text', 'name', array( 'validate' => 'acme:record' ) ) ) ) ) ) + array(
			'section' => array(
				'id'     => 'extra',
				'tab'    => $this->prefix,
				'label'  => 'Extra',
				'fields' => array( $this->field( 'text', 'extra', array( 'validate' => 'acme:record' ) ) ),
			),
		);

		$tab_save     = $this->save( $spec, '', array( "{$this->prefix}_name" => 'a' ) );
		$section_save = $this->save( $spec, 'extra', array( "{$this->prefix}_extra" => 'b' ) );

		self::assertSame( array( array( 'validate', "{$this->prefix}_name", 'a' ) ), $tab_save['records'] );
		self::assertSame( array( array( 'validate', "{$this->prefix}_extra", 'b' ) ), $section_save['records'] );
	}

	public function test_a_contributed_field_is_owned_by_the_section_it_joins(): void {
		$spec = $this->tab_spec( array( '' => self::section( 'General', array( $this->field( 'text', 'name' ) ) ) ) ) + array(
			'contributed' => array(
				'tab'     => $this->prefix,
				'section' => '',
				'fields'  => array( $this->field( 'text', 'extra', array( 'validate' => 'acme:append' ) ) ),
			),
		);

		$this->save( $spec, '', array( "{$this->prefix}_extra" => 'c' ) );

		self::assertSame( 'c!', \get_option( "{$this->prefix}_extra" ) );
	}

	public function test_a_custom_type_field_renders_in_woocommerces_row_through_its_render_callable(): void {
		\update_option( "{$this->prefix}_widget", 'stored' );
		// WooCommerce's title and sectionend entries open and close the table that holds the rows.
		$fields = array(
			array(
				'type'  => 'title',
				'id'    => "{$this->prefix}_options",
				'title' => 'Options',
			),
			$this->custom_field( array( 'title' => "Customer's <b>widget</b>" ) ),
			array(
				'type' => 'sectionend',
				'id'   => "{$this->prefix}_options",
			),
		);
		$spec   = $this->tab_spec( array( '' => self::section( 'General', $fields ) ) );

		$opened = $this->open( $spec );
		$row    = self::document( $opened['body'] )->querySelector( "form#mainform #{$this->prefix}_widget" )?->closest( 'tr' );

		self::assertNotNull( $row );
		self::assertSame( "Customer's <b>widget</b>", \trim( (string) $row->querySelector( 'th.titledesc label' )?->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		self::assertSame( "{$this->prefix}_widget", $row->querySelector( 'th.titledesc label' )?->getAttribute( 'for' ) );
		self::assertNotNull( $row->querySelector( "td.forminp input.acme-widget#{$this->prefix}_widget" ) );
		self::assertSame( array( array( 'render', "{$this->prefix}_widget", 'stored', "{$this->prefix}_widget", "{$this->prefix}_widget" ) ), $opened['records'] );
	}

	public function test_a_custom_type_field_saves_through_its_validate(): void {
		$spec = $this->tab_spec( array( '' => self::section( 'General', array( $this->custom_field( array( 'validate' => 'acme:append' ) ) ) ) ) );

		$this->save( $spec, '', array( "{$this->prefix}_widget" => 'w' ) );

		self::assertSame( 'w!', \get_option( "{$this->prefix}_widget" ) );
	}

	public function test_a_custom_type_field_without_validate_is_rejected(): void {
		\update_option( "{$this->prefix}_widget", 'old' );
		$spec = $this->tab_spec( array( '' => self::section( 'General', array( $this->custom_field() ) ) ) );

		$saved = self::document( $this->save( $spec, '', array( "{$this->prefix}_widget" => 'w' ) )['body'] );

		self::assertSame( 'old', \get_option( "{$this->prefix}_widget" ) );
		self::assertSame( array( 'Widget was not saved, so it keeps its previous value. This field cannot be saved because nothing validates its value.' ), self::texts( $saved, '#message.error' ) );
	}

	public function test_a_custom_type_field_the_helper_does_not_own_is_not_printed(): void {
		$tab = $this->add_tab_in_process(
			array(
				'' => $this->in_process_section(
					array(
						$this->field(
							'acme_widget',
							'widget',
							array(
								'render'   => static fn (): string => '<input class="acme-widget" />',
								'validate' => static fn ( mixed $raw ): mixed => $raw,
							)
						),
					)
				),
			)
		);
		$tab->get_settings_for_section( '' );

		\ob_start();
		\do_action( 'woocommerce_admin_field_acme_widget', $this->field( 'acme_widget', 'foreign', array( 'value' => '' ) ) );

		self::assertSame( '', \ob_get_clean() );
	}

	public function test_a_checkboxes_group_renders_and_round_trips_its_choices(): void {
		\update_option( "{$this->prefix}_colors", array( 'red' ) );
		$spec = $this->tab_spec( array( '' => self::section( 'General', array( $this->field( 'checkboxes', 'colors', array( 'options' => self::COLORS ) ) ) ) ) );

		$form = self::document( $this->open( $spec )['body'] );
		self::assertCount( 3, $form->querySelectorAll( "form#mainform fieldset input[type=\"checkbox\"][name=\"{$this->prefix}_colors[]\"]" ) );

		$this->save( $spec, '', array( "{$this->prefix}_colors" => array( 'blue', 'teal' ) ) );
		self::assertSame( array( 'blue', 'teal' ), \get_option( "{$this->prefix}_colors" ) );

		$this->save( $spec, '', array( "{$this->prefix}_colors" => array() ) );
		self::assertSame( array(), \get_option( "{$this->prefix}_colors" ) );
	}

	public function test_a_checkboxes_group_rejects_an_unknown_choice(): void {
		\update_option( "{$this->prefix}_colors", array( 'red' ) );
		$spec = $this->tab_spec( array( '' => self::section( 'General', array( $this->field( 'checkboxes', 'colors', array( 'options' => self::COLORS ) ) ) ) ) );

		$saved = self::document( $this->save( $spec, '', array(), array( "{$this->prefix}_colors" => array( 'pink' ) ) )['body'] );

		self::assertSame( array( 'red' ), \get_option( "{$this->prefix}_colors" ) );
		self::assertSame( array( 'Colors was not saved, so it keeps its previous value. Choose one of the available options.' ), self::texts( $saved, '#message.error' ) );
	}

	protected function tab_spec( array $sections ): array {
		return array(
			'tab' => array(
				'id'       => $this->prefix,
				'label'    => 'Acme',
				'sections' => $sections,
			),
		);
	}

	protected static function section( string $label, array $fields ): array {
		return array(
			'label'  => $label,
			'fields' => $fields,
		);
	}

	protected function field( string $type, string $name, array $keys = array() ): array {
		return $keys + array(
			'type'  => $type,
			'id'    => "{$this->prefix}_$name",
			'title' => \ucfirst( $name ),
		);
	}

	protected function custom_field( array $keys = array() ): array {
		return $this->field( "{$this->prefix}_widget", 'widget', $keys + array( 'render' => 'acme:render' ) );
	}

	protected function tab_definition( array $sections ): \Closure {
		return static fn (): array => array(
			'label'    => 'Acme',
			'sections' => $sections,
		);
	}

	protected function section_definition( string $tab, array $fields ): \Closure {
		return static fn (): array => array(
			'tab'    => $tab,
			'label'  => 'Acme',
			'fields' => static fn (): array => $fields,
		);
	}

	protected function in_process_section( array $fields ): array {
		return array(
			'label'  => 'General',
			'fields' => static fn (): array => $fields,
		);
	}

	// WooCommerce builds its settings pages once per request, so the test's tab joins a fresh build.
	protected function add_tab_in_process( array $sections ): SettingsTab {
		$this->reset_settings_pages();
		$tab = new SettingsTab(
			$this->prefix,
			static fn (): array => array(
				'label'    => 'Acme',
				'sections' => $sections,
			)
		);
		\add_filter(
			'woocommerce_get_settings_pages',
			static function ( array $pages ) use ( $tab ): array {
				$pages[] = $tab;

				return $pages;
			}
		);

		return $tab;
	}

	protected function reset_settings_pages(): void {
		\Closure::bind(
			static function (): void {
				self::$settings = array();
			},
			null,
			\WC_Admin_Settings::class
		)();
	}

	// The server holds only WooCommerce's settings routes, because a full build keeps memory for the rest of the run.
	protected function rest( string $method, string $route, array $params = array() ): \WP_REST_Response {
		\wp_set_current_user( $this->admin );
		\remove_all_actions( 'rest_api_init' );
		\add_action( 'rest_api_init', array( \WC(), 'register_wp_admin_settings' ) );
		\add_action(
			'rest_api_init',
			static function (): void {
				( new \WC_REST_Setting_Options_Controller() )->register_routes();
			}
		);
		$GLOBALS['wp_rest_server'] = null;

		$request = new \WP_REST_Request( $method, $route );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return \rest_get_server()->dispatch( $request );
	}

	protected function open( array $spec, string $section = '', ?string $tab = null ): array {
		return $this->admin_request( 'GET', $this->settings_path( $tab ?? $this->prefix, $section ), $this->admin, self::FIXTURE, $spec );
	}

	// WooCommerce enables its save button with script, so the serialized form lacks it.
	protected function save( array $spec, string $section, array $changes, array $tampered = array(), ?string $tab = null ): array {
		$data = \array_replace( self::serialize_form( $this->open( $spec, $section, $tab )['body'], 'form#mainform', $changes ), $tampered ) + array( 'save' => 'Save changes' );

		return $this->admin_request( 'POST', $this->settings_path( $tab ?? $this->prefix, $section ), $this->admin, self::FIXTURE, $spec, $data );
	}

	protected function settings_path( string $tab, string $section ): string {
		return "admin.php?page=wc-settings&tab=$tab" . ( '' === $section ? '' : "&section=$section" );
	}

	protected static function texts( \Dom\HTMLDocument $document, string $selector ): array {
		$texts = array();
		foreach ( $document->querySelectorAll( $selector ) as $element ) {
			$texts[] = \trim( $element->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		}

		return $texts;
	}
}
