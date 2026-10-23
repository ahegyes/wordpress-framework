<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\WooCommerce\ProductData;

use DeepWebSolutions\Framework\Shared\Exception\InvalidArgumentException;
use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesStorageTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\ProvidesHelperIdsTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\RecordedDefinition;
use DeepWebSolutions\Framework\Tests\Integration\Support\RunsAdminRequestsTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\SerializesFormsTrait;
use DeepWebSolutions\Framework\WooCommerce\ProductData\ProductDataTab;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( ProductDataTab::class )]
final class ProductDataTabTest extends TestCase {
	use CreatesUsersTrait;
	use IsolatesHooksTrait;
	use IsolatesStorageTrait;
	use ProductFixturesTrait;
	use ProvidesHelperIdsTrait;
	use RunsAdminRequestsTrait;
	use SerializesFormsTrait;

	protected const string FIXTURE = __DIR__ . '/../../../Fixtures/WooCommerce/acme-product-data.php';

	protected const array COLORS = array(
		'red'  => 'Red',
		'blue' => 'Blue',
		'teal' => 'Teal',
	);

	protected int $admin;

	#[\Override]
	protected function setUp(): void {
		$this->admin = $this->make_user( 'acme_product_data_admin', 'administrator' );
	}

	public static function disabled_checkboxes(): array {
		return array(
			'custom-attribute' => array( array( 'custom_attributes' => array( 'disabled' => 'disabled' ) ) ),
			'top-level'        => array( array( 'disabled' => true ) ),
		);
	}

	public static function silent_and_worded_rejections(): array {
		return array(
			'worded' => array( 'acme:reject', "Customer's <b>note</b> was not saved, so it keeps its previous value. Use at most four characters." ),
			'silent' => array( 'acme:reject-silently', "Customer's <b>note</b> was not saved because its value is not valid, so it keeps its previous value." ),
		);
	}

	public static function multi_value_selections(): array {
		return array(
			'two'  => array( array( 'red', 'teal' ), array( 'red', 'teal' ) ),
			'none' => array( array(), array() ),
		);
	}

	#[DataProvider( 'invalid_helper_ids' )]
	public function test_an_invalid_tab_id_is_rejected( string $id ): void {
		$this->expectException( InvalidArgumentException::class );

		new ProductDataTab( $id, new RecordedDefinition( static fn (): ?array => null ) );
	}

	#[DataProvider( 'v1_helper_ids' )]
	public function test_every_v1_key_is_a_valid_tab_id( string $id ): void {
		$tab = new ProductDataTab( $id, new RecordedDefinition( static fn (): ?array => null ) );
		$tab->register_hooks();

		self::assertSame( 10, \has_filter( 'woocommerce_product_data_tabs', array( $tab, 'add_tab' ) ) );
	}

	public function test_the_definition_is_evaluated_only_when_the_edit_screen_needs_it(): void {
		$definition = new RecordedDefinition( static fn (): ?array => null );
		$tab        = new ProductDataTab( $this->prefix, $definition );

		$tab->register_hooks();

		self::assertSame( array(), $definition->calls );
		self::assertSame( 10, \has_filter( 'woocommerce_product_data_tabs', array( $tab, 'add_tab' ) ) );
		self::assertSame( 10, \has_action( 'woocommerce_product_data_panels', array( $tab, 'render_panel' ) ) );
	}

	public function test_the_tab_and_its_panel_render_on_the_product_edit_screen(): void {
		$product = $this->make_product();
		$spec    = $this->spec(
			array( $this->group( array( $this->field( 'text', 'name' ) ), array( 'class' => array( 'acme-group' ) ) ) ),
			array( 'class' => array( 'show_if_simple' ) )
		);

		$screen = self::document( $this->edit_screen( $spec, $product->get_id() )['body'] );

		$tab = $screen->querySelector( "ul.product_data_tabs li.{$this->prefix}_tab.show_if_simple a[href=\"#{$this->prefix}_product_data\"]" );
		self::assertNotNull( $tab );
		self::assertSame( 'Acme', \trim( $tab->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		self::assertNotNull( $screen->querySelector( "form#post div.panel.woocommerce_options_panel[id=\"{$this->prefix}_product_data\"] div.options_group.acme-group p.form-field input[name=\"_{$this->prefix}_name\"]" ) );
	}

	public function test_the_tab_sits_by_its_priority_among_woocommerces_tabs(): void {
		$product = $this->make_product();

		$default = self::document( $this->edit_screen( $this->spec( array() ), $product->get_id() )['body'] );
		$late    = self::document( $this->edit_screen( $this->spec( array(), array( 'priority' => 75 ) ), $product->get_id() )['body'] );

		self::assertSame( array( 'variations', $this->prefix, 'advanced' ), \array_values( \array_intersect( $this->tab_keys( $default ), array( 'variations', $this->prefix, 'advanced' ) ) ) );
		self::assertSame( array( 'advanced', $this->prefix ), \array_values( \array_intersect( $this->tab_keys( $late ), array( 'advanced', $this->prefix ) ) ) );
	}

	public function test_a_product_predating_its_fields_renders_their_defaults(): void {
		$product = $this->make_product();
		$spec    = $this->spec(
			array(
				$this->group(
					array(
						$this->field( 'checkbox', 'flag', array( 'default' => 'yes' ) ),
						$this->field(
							'select',
							'color',
							array(
								'options' => self::COLORS,
								'default' => 'blue',
							)
						),
					)
				),
			)
		);

		$screen = self::document( $this->edit_screen( $spec, $product->get_id() )['body'] );

		self::assertTrue( $this->control( $screen, 'flag' )->hasAttribute( 'checked' ) );
		self::assertSame( array( 'blue' ), $this->selected( $screen, 'color' ) );
	}

	public function test_stored_empty_values_render_as_stored_never_as_the_default(): void {
		$product = $this->make_product(
			array(
				"_{$this->prefix}_name"   => '',
				"_{$this->prefix}_colors" => array(),
			)
		);
		$spec    = $this->spec(
			array(
				$this->group(
					array(
						$this->field( 'text', 'name', array( 'default' => 'fallback' ) ),
						$this->field(
							'multiselect',
							'colors',
							array(
								'options' => self::COLORS,
								'default' => array( 'red' ),
							)
						),
					)
				),
			)
		);

		$screen = self::document( $this->edit_screen( $spec, $product->get_id() )['body'] );

		self::assertSame( '', $this->control( $screen, 'name' )->getAttribute( 'value' ) );
		self::assertSame( array(), $this->selected( $screen, 'colors' ) );
	}

	public function test_a_field_whose_name_differs_from_its_id_renders_the_value_stored_under_its_name(): void {
		$product = $this->make_product(
			array(
				"_{$this->prefix}_stored" => 'right',
				"_{$this->prefix}_alias"  => 'wrong',
			)
		);
		$spec    = $this->spec( array( $this->group( array( $this->field( 'text', 'alias', array( 'name' => "_{$this->prefix}_stored" ) ) ) ) ) );

		$control = self::document( $this->edit_screen( $spec, $product->get_id() )['body'] )->querySelector( "[id=\"_{$this->prefix}_alias\"]" );

		self::assertSame( "_{$this->prefix}_stored", $control?->getAttribute( 'name' ) );
		self::assertSame( 'right', $control?->getAttribute( 'value' ) );
	}

	public function test_a_field_renders_its_stored_value_not_woocommerces_filtered_value(): void {
		$product = $this->make_product( array( "_{$this->prefix}_name" => 'stored' ) );
		$spec    = $this->spec( array( $this->group( array( $this->field( 'text', 'name' ) ) ) ), array( 'filtered' => "_{$this->prefix}_name" ) );

		$screen = self::document( $this->edit_screen( $spec, $product->get_id() )['body'] );

		self::assertSame( 'stored', $this->control( $screen, 'name' )->getAttribute( 'value' ) );
	}

	public function test_a_custom_type_field_renders_its_control_in_woocommerces_row_through_its_render_callable(): void {
		$product = $this->make_product( array( "_{$this->prefix}_widget" => 'stored' ) );
		$spec    = $this->spec( array( $this->group( array( $this->custom_field() ) ) ) );

		$request = $this->edit_screen( $spec, $product->get_id() );
		$screen  = self::document( $request['body'] );

		$row = $screen->querySelector( "p.form-field._{$this->prefix}_widget_field" );
		self::assertSame( 'Widget', \trim( (string) $row?->querySelector( "label[for=\"_{$this->prefix}_widget\"]" )?->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		self::assertSame( 'stored', $row?->querySelector( 'input.acme-widget' )?->getAttribute( 'value' ) );
		self::assertSame( array( array( 'render', "_{$this->prefix}_widget", 'stored', "_{$this->prefix}_widget", "_{$this->prefix}_widget" ) ), $request['records'] );
	}

	public function test_a_checkboxes_group_renders_in_a_fieldset_named_by_its_label(): void {
		$product = $this->make_product( array( "_{$this->prefix}_colors" => array( 'blue' ) ) );
		$spec    = $this->spec(
			array(
				$this->group(
					array(
						$this->field(
							'checkboxes',
							'colors',
							array(
								'options'     => self::COLORS,
								'description' => 'Pick any.',
							)
						),
					)
				),
			)
		);

		$fieldset = self::document( $this->edit_screen( $spec, $product->get_id() )['body'] )->querySelector( "fieldset.form-field._{$this->prefix}_colors_field" );

		self::assertSame( 'Colors', \trim( (string) $fieldset?->querySelector( 'legend' )?->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		self::assertCount( 1, $fieldset?->querySelectorAll( 'legend' ) ?? array() );
		self::assertSame( '', $fieldset?->querySelector( "input[type=hidden][name=\"_{$this->prefix}_colors\"]" )?->getAttribute( 'value' ) );
		self::assertSame( array( 'blue' ), $this->checked( $fieldset, "_{$this->prefix}_colors[]" ) );
		self::assertSame( 'Pick any.', \trim( (string) $fieldset?->querySelector( 'p.description' )?->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
	}

	public function test_a_multiselect_posts_an_empty_value_before_its_choices(): void {
		$product = $this->make_product( array( "_{$this->prefix}_colors" => array( 'red', 'teal' ) ) );
		$spec    = $this->spec( array( $this->group( array( $this->field( 'multiselect', 'colors', array( 'options' => self::COLORS ) ) ) ) ) );

		$screen = self::document( $this->edit_screen( $spec, $product->get_id() )['body'] );

		$hidden = $screen->querySelector( "input[type=hidden][name=\"_{$this->prefix}_colors\"]" );
		self::assertSame( '', $hidden?->getAttribute( 'value' ) );
		self::assertSame( "_{$this->prefix}_colors[]", $hidden?->nextElementSibling?->querySelector( 'select' )?->getAttribute( 'name' ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		self::assertTrue( $this->control( $screen, 'colors' )->hasAttribute( 'multiple' ) );
		self::assertSame( array( 'red', 'teal' ), $this->selected( $screen, 'colors' ) );
	}

	public function test_a_number_field_accepts_any_decimal_unless_it_sets_its_own_step(): void {
		$product = $this->make_product( array( "_{$this->prefix}_weight" => '1.25' ) );
		$spec    = $this->spec(
			array(
				$this->group(
					array(
						$this->field( 'number', 'weight' ),
						$this->field( 'number', 'count', array( 'custom_attributes' => array( 'step' => '1' ) ) ),
					)
				),
			)
		);

		$screen = self::document( $this->edit_screen( $spec, $product->get_id() )['body'] );

		self::assertSame( 'any', $this->control( $screen, 'weight' )->getAttribute( 'step' ) );
		self::assertSame( '1', $this->control( $screen, 'count' )->getAttribute( 'step' ) );
	}

	public function test_an_enabled_checkbox_posts_no_from_before_its_row_when_unchecked(): void {
		$product = $this->make_product();
		$spec    = $this->spec( array( $this->group( array( $this->field( 'checkbox', 'flag' ) ) ) ) );

		$screen = self::document( $this->edit_screen( $spec, $product->get_id() )['body'] );

		$hidden = $screen->querySelectorAll( "input[type=hidden][name=\"_{$this->prefix}_flag\"]" );
		self::assertCount( 1, $hidden );
		self::assertSame( 'no', $hidden[0]->getAttribute( 'value' ) );
		self::assertSame( $this->control( $screen, 'flag' ), $hidden[0]->nextElementSibling?->querySelector( 'input[type=checkbox]' ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		self::assertSame( 'yes', $this->control( $screen, 'flag' )->getAttribute( 'value' ) );
	}

	#[DataProvider( 'disabled_checkboxes' )]
	public function test_a_disabled_checkbox_renders_disabled_without_its_hidden_input( array $keys ): void {
		$product = $this->make_product( array( "_{$this->prefix}_flag" => 'yes' ) );
		$spec    = $this->spec( array( $this->group( array( $this->field( 'checkbox', 'flag', $keys ) ) ) ) );

		$screen = self::document( $this->edit_screen( $spec, $product->get_id() )['body'] );

		self::assertTrue( $this->control( $screen, 'flag' )->hasAttribute( 'disabled' ) );
		self::assertNull( $screen->querySelector( "input[type=hidden][name=\"_{$this->prefix}_flag\"]" ) );
	}

	public function test_an_unlabelled_field_is_labelled_by_its_id(): void {
		$product = $this->make_product();
		$field   = $this->field( 'text', 'name' );
		unset( $field['label'] );

		$screen = self::document( $this->edit_screen( $this->spec( array( $this->group( array( $field ) ) ) ), $product->get_id() )['body'] );

		self::assertSame( "_{$this->prefix}_name", \trim( (string) $screen->querySelector( "label[for=\"_{$this->prefix}_name\"]" )?->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
	}

	public function test_a_field_of_an_unknown_type_without_render_prints_nothing(): void {
		$product = $this->make_product();
		$spec    = $this->spec( array( $this->group( array( $this->field( 'acme_unknown', 'odd' ), $this->field( 'text', 'name' ) ) ) ) );

		$screen = self::document( $this->edit_screen( $spec, $product->get_id() )['body'] );

		self::assertNull( $screen->querySelector( "[name=\"_{$this->prefix}_odd\"]" ) );
		self::assertNotNull( $this->control( $screen, 'name' ) );
	}

	public function test_the_definition_receives_the_tabs_id_and_each_product_once(): void {
		$product    = $this->make_product();
		$definition = new RecordedDefinition(
			static fn (): array => array(
				'label'  => 'Acme',
				'groups' => array(),
			)
		);
		$tab        = new ProductDataTab( $this->prefix, $definition );

		$GLOBALS['product_object'] = $product;
		try {
			$tab->add_tab( array() );
			\ob_start();
			$tab->render_panel();
			\ob_end_clean();
		} finally {
			unset( $GLOBALS['product_object'] );
		}

		self::assertSame( array( array( $this->prefix, $product ) ), $definition->calls );
	}

	public function test_an_unsupported_product_shows_neither_the_tab_nor_its_panel(): void {
		$simple   = $this->make_product();
		$variable = $this->make_product( array(), 'variable' );
		$spec     = $this->spec( array( $this->group( array( $this->field( 'text', 'name' ) ) ) ), array( 'supports' => 'acme:simple' ) );

		$supported   = self::document( $this->edit_screen( $spec, $simple->get_id() )['body'] );
		$unsupported = self::document( $this->edit_screen( $spec, $variable->get_id() )['body'] );

		self::assertContains( $this->prefix, $this->tab_keys( $supported ) );
		self::assertNotContains( $this->prefix, $this->tab_keys( $unsupported ) );
		self::assertNull( $unsupported->querySelector( "[id=\"{$this->prefix}_product_data\"]" ) );
	}

	public function test_a_per_product_field_list_receives_the_product_being_edited(): void {
		$product = $this->make_product();
		$spec    = $this->spec( array( $this->group( array( $this->field( 'text', 'name' ) ), array( 'per_product' => true ) ) ) );

		$request = $this->edit_screen( $spec, $product->get_id() );

		self::assertNotNull( $this->control( self::document( $request['body'] ), 'name' ) );
		self::assertSame( array( array( 'fields', $product->get_id() ) ), $request['records'] );
	}

	public function test_a_group_contributed_through_the_groups_filter_renders_for_the_product_being_edited(): void {
		$product = $this->make_product( array( "_{$this->prefix}_extra" => 'contributed' ) );
		$spec    = $this->spec( array(), array( 'contributed' => array( $this->field( 'text', 'extra' ) ) ) );

		$request = $this->edit_screen( $spec, $product->get_id() );

		self::assertSame( 'contributed', $this->control( self::document( $request['body'] ), 'extra' )->getAttribute( 'value' ) );
		self::assertSame( array( array( 'groups', $product->get_id() ) ), $request['records'] );
	}

	public function test_a_new_product_renders_the_tab_with_its_defaults(): void {
		$spec = $this->spec( array( $this->group( array( $this->field( 'text', 'name', array( 'default' => 'fresh' ) ) ) ) ) );

		$screen = self::document( $this->new_screen( $spec )['body'] );

		self::assertContains( $this->prefix, $this->tab_keys( $screen ) );
		self::assertSame( 'fresh', $this->control( $screen, 'name' )->getAttribute( 'value' ) );
	}

	public function test_labels_and_values_are_escaped(): void {
		$product = $this->make_product( array( "_{$this->prefix}_name" => '"><script>acme()</script>' ) );
		$spec    = $this->spec( array( $this->group( array( $this->field( 'text', 'name', array( 'label' => 'Name <script>acme()</script>' ) ) ) ) ) );

		$screen = self::document( $this->edit_screen( $spec, $product->get_id() )['body'] );

		self::assertSame( '"><script>acme()</script>', $this->control( $screen, 'name' )->getAttribute( 'value' ) );
		self::assertNull( $screen->querySelector( "[id=\"{$this->prefix}_product_data\"] script" ) );
	}

	public function test_registering_hooks_the_save_onto_the_product_woocommerce_saves(): void {
		$tab = new ProductDataTab( $this->prefix, new RecordedDefinition( static fn (): ?array => null ) );

		$tab->register_hooks();

		self::assertSame( 10, \has_action( 'woocommerce_admin_process_product_object', array( $tab, 'save_fields' ) ) );
	}

	public function test_an_unchanged_save_stores_the_defaults_of_a_product_predating_its_fields(): void {
		$product = $this->make_product();
		$spec    = $this->spec(
			array(
				$this->group(
					array(
						$this->field( 'checkbox', 'flag', array( 'default' => 'yes' ) ),
						$this->field(
							'select',
							'color',
							array(
								'options' => self::COLORS,
								'default' => 'blue',
							)
						),
					)
				),
			)
		);

		$this->save( $spec, $product->get_id() );

		self::assertSame( array( 'yes' ), $this->meta( $product, 'flag' ) );
		self::assertSame( array( 'blue' ), $this->meta( $product, 'color' ) );
	}

	public function test_stored_empty_values_stay_stored_on_an_unchanged_save(): void {
		$product = $this->make_product(
			array(
				"_{$this->prefix}_name"   => '',
				"_{$this->prefix}_colors" => array(),
			)
		);
		$spec    = $this->spec(
			array(
				$this->group(
					array(
						$this->field( 'text', 'name', array( 'default' => 'fallback' ) ),
						$this->field(
							'multiselect',
							'colors',
							array(
								'options' => self::COLORS,
								'default' => array( 'red' ),
							)
						),
					)
				),
			)
		);

		$this->save( $spec, $product->get_id() );

		self::assertSame( array( '' ), $this->meta( $product, 'name' ) );
		self::assertSame( array( array() ), $this->meta( $product, 'colors' ) );
	}

	public function test_a_field_whose_name_differs_from_its_id_saves_under_its_name(): void {
		$product = $this->make_product( array( "_{$this->prefix}_alias" => 'kept' ) );
		$spec    = $this->spec( array( $this->group( array( $this->field( 'text', 'alias', array( 'name' => "_{$this->prefix}_stored[]" ) ) ) ) ) );

		$this->save( $spec, $product->get_id(), array( "_{$this->prefix}_stored" => 'new' ) );

		self::assertSame( array( 'new' ), $this->meta( $product, 'stored' ) );
		self::assertSame( array( 'kept' ), $this->meta( $product, 'alias' ) );
	}

	public function test_text_holding_html_entities_survives_an_unchanged_save(): void {
		$stored  = 'Use &lt;b&gt;bold&lt;/b&gt; &amp; more';
		$product = $this->make_product( array( "_{$this->prefix}_name" => $stored ) );
		$spec    = $this->spec( array( $this->group( array( $this->field( 'text', 'name' ) ) ) ) );

		$screen = self::document( $this->edit_screen( $spec, $product->get_id() )['body'] );
		$this->save( $spec, $product->get_id() );

		self::assertSame( $stored, $this->control( $screen, 'name' )->getAttribute( 'value' ) );
		self::assertSame( array( $stored ), $this->meta( $product, 'name' ) );
	}

	public function test_a_field_without_validate_is_sanitized_by_its_type(): void {
		$product = $this->make_product();
		$spec    = $this->spec( array( $this->group( array( $this->field( 'text', 'name' ) ) ) ) );

		$this->save( $spec, $product->get_id(), array( "_{$this->prefix}_name" => ' <b>bold</b> ' ) );

		self::assertSame( array( 'bold' ), $this->meta( $product, 'name' ) );
	}

	public function test_submissions_are_stored_unslashed_on_a_new_and_an_existing_key(): void {
		$product = $this->make_product( array( "_{$this->prefix}_existing" => 'old' ) );
		$spec    = $this->spec( array( $this->group( array( $this->field( 'text', 'fresh' ), $this->field( 'text', 'existing' ) ) ) ) );

		$this->save(
			$spec,
			$product->get_id(),
			array(
				"_{$this->prefix}_fresh"    => "O'Brien C:\\dir",
				"_{$this->prefix}_existing" => "O'Brien C:\\dir",
			)
		);

		self::assertSame( array( "O'Brien C:\\dir" ), $this->meta( $product, 'fresh' ) );
		self::assertSame( array( "O'Brien C:\\dir" ), $this->meta( $product, 'existing' ) );
	}

	public function test_validate_receives_the_unslashed_submission_alone_and_its_return_value_is_stored(): void {
		$product = $this->make_product();
		$spec    = $this->spec( array( $this->group( array( $this->field( 'text', 'name', array( 'validate' => 'acme:append' ) ) ) ) ) );

		$saved = $this->save( $spec, $product->get_id(), array( "_{$this->prefix}_name" => "O'Brien" ) );

		self::assertSame( array( "O'Brien!" ), $this->meta( $product, 'name' ) );
		self::assertSame( array( array( 'validate', "_{$this->prefix}_name", "O'Brien" ) ), $saved['records'] );
	}

	public function test_a_rejected_choice_keeps_the_stored_value_and_says_what_to_choose(): void {
		$product = $this->make_product( array( "_{$this->prefix}_color" => 'red' ) );
		$spec    = $this->spec( array( $this->group( array( $this->field( 'select', 'color', array( 'options' => self::COLORS ) ) ) ) ) );

		$this->save( $spec, $product->get_id(), array(), array( "_{$this->prefix}_color" => 'purple' ) );

		self::assertSame( array( 'red' ), $this->meta( $product, 'color' ) );
		self::assertSame( array( 'Color was not saved, so it keeps its previous value. Choose one of the available options.' ), $this->errors( $spec, $product ) );
	}

	#[DataProvider( 'silent_and_worded_rejections' )]
	public function test_a_rejection_keeps_the_stored_value_and_shows_its_message_once_as_text( string $behavior, string $message ): void {
		$product = $this->make_product( array( "_{$this->prefix}_note" => 'old' ) );
		$spec    = $this->spec(
			array(
				$this->group(
					array(
						$this->field(
							'text',
							'note',
							array(
								'label'    => "Customer's <b>note</b>",
								'validate' => $behavior,
							)
						),
					)
				),
			)
		);

		$this->save( $spec, $product->get_id(), array( "_{$this->prefix}_note" => 'too long' ) );
		$next  = self::document( $this->edit_screen( $spec, $product->get_id() )['body'] );
		$after = self::document( $this->edit_screen( $spec, $product->get_id() )['body'] );

		self::assertSame( array( 'old' ), $this->meta( $product, 'note' ) );
		self::assertSame( array( $message ), self::texts( $next, '#woocommerce_errors p' ) );
		self::assertNull( $next->querySelector( '#woocommerce_errors b' ) );
		self::assertSame( array(), self::texts( $after, '#woocommerce_errors p' ) );
	}

	public function test_an_unchecked_checkbox_stores_no(): void {
		$product = $this->make_product( array( "_{$this->prefix}_flag" => 'yes' ) );
		$spec    = $this->spec( array( $this->group( array( $this->field( 'checkbox', 'flag' ) ) ) ) );

		$this->save( $spec, $product->get_id(), array( "_{$this->prefix}_flag" => false ) );

		self::assertSame( array( 'no' ), $this->meta( $product, 'flag' ) );
	}

	#[DataProvider( 'disabled_checkboxes' )]
	public function test_a_disabled_checkbox_keeps_its_value_even_when_a_value_is_posted( array $keys ): void {
		$product = $this->make_product( array( "_{$this->prefix}_flag" => 'yes' ) );
		$spec    = $this->spec( array( $this->group( array( $this->field( 'checkbox', 'flag', $keys ) ) ) ) );

		$this->save( $spec, $product->get_id(), array(), array( "_{$this->prefix}_flag" => 'no' ) );

		self::assertSame( array( 'yes' ), $this->meta( $product, 'flag' ) );
	}

	public function test_a_field_the_form_did_not_post_keeps_its_stored_value(): void {
		$product = $this->make_product( array( "_{$this->prefix}_name" => 'old' ) );
		$spec    = $this->spec( array( $this->group( array( $this->field( 'text', 'name' ) ) ) ) );

		$this->save( $spec, $product->get_id(), array(), array(), array( "_{$this->prefix}_name" ) );

		self::assertSame( array( 'old' ), $this->meta( $product, 'name' ) );
	}

	public function test_a_posted_key_outside_the_definition_is_never_stored(): void {
		$product = $this->make_product();
		$spec    = $this->spec( array( $this->group( array( $this->field( 'text', 'name' ) ) ) ) );

		$this->save( $spec, $product->get_id(), array(), array( "_{$this->prefix}_other" => 'injected' ) );

		self::assertSame( array(), $this->meta( $product, 'other' ) );
	}

	#[DataProvider( 'multi_value_selections' )]
	public function test_a_multiselect_stores_exactly_its_selected_choices_under_its_key( array $selection, array $stored ): void {
		$product = $this->make_product( array( "_{$this->prefix}_colors" => array( 'blue' ) ) );
		$spec    = $this->spec( array( $this->group( array( $this->field( 'multiselect', 'colors', array( 'options' => self::COLORS ) ) ) ) ) );

		$this->save( $spec, $product->get_id(), array( "_{$this->prefix}_colors" => $selection ) );

		self::assertSame( array( $stored ), $this->meta( $product, 'colors' ) );
	}

	#[DataProvider( 'multi_value_selections' )]
	public function test_a_checkboxes_group_stores_exactly_its_checked_choices( array $selection, array $stored ): void {
		$product = $this->make_product( array( "_{$this->prefix}_colors" => array( 'blue' ) ) );
		$spec    = $this->spec( array( $this->group( array( $this->field( 'checkboxes', 'colors', array( 'options' => self::COLORS ) ) ) ) ) );

		$this->save( $spec, $product->get_id(), array( "_{$this->prefix}_colors" => $selection ) );

		self::assertSame( array( $stored ), $this->meta( $product, 'colors' ) );
	}

	public function test_a_checkboxes_group_rejects_an_unknown_choice(): void {
		$product = $this->make_product( array( "_{$this->prefix}_colors" => array( 'blue' ) ) );
		$spec    = $this->spec( array( $this->group( array( $this->field( 'checkboxes', 'colors', array( 'options' => self::COLORS ) ) ) ) ) );

		$this->save( $spec, $product->get_id(), array(), array( "_{$this->prefix}_colors" => array( 'purple' ) ) );

		self::assertSame( array( array( 'blue' ) ), $this->meta( $product, 'colors' ) );
		self::assertSame( array( 'Colors was not saved, so it keeps its previous value. Choose one of the available options.' ), $this->errors( $spec, $product ) );
	}

	public function test_a_custom_type_field_stores_its_validates_return_value_under_its_key(): void {
		$product = $this->make_product();
		$spec    = $this->spec( array( $this->group( array( $this->custom_field( array( 'validate' => 'acme:append' ) ) ) ) ) );

		$saved = $this->save( $spec, $product->get_id(), array( "_{$this->prefix}_widget" => 'v' ) );

		self::assertSame( array( 'v!' ), $this->meta( $product, 'widget' ) );
		self::assertContains( array( 'validate', "_{$this->prefix}_widget", 'v' ), $saved['records'] );
	}

	public function test_a_custom_type_field_without_validate_is_rejected(): void {
		$product = $this->make_product( array( "_{$this->prefix}_widget" => 'old' ) );
		$spec    = $this->spec( array( $this->group( array( $this->custom_field() ) ) ) );

		$this->save( $spec, $product->get_id(), array( "_{$this->prefix}_widget" => 'v' ) );

		self::assertSame( array( 'old' ), $this->meta( $product, 'widget' ) );
		self::assertSame( array( 'Widget was not saved, so it keeps its previous value. This field cannot be saved because nothing validates its value.' ), $this->errors( $spec, $product ) );
	}

	public function test_an_unsupported_product_never_saves_the_tabs_fields(): void {
		$product = $this->make_product( array(), 'variable' );
		$spec    = $this->spec( array( $this->group( array( $this->field( 'text', 'name' ) ) ) ), array( 'supports' => 'acme:simple' ) );

		$this->save( $spec, $product->get_id(), array(), array( "_{$this->prefix}_name" => 'injected' ) );

		self::assertSame( array(), $this->meta( $product, 'name' ) );
	}

	public function test_a_v1_meta_key_round_trips_through_the_panel(): void {
		$key     = "_{$this->prefix}-wrwc_warranty-type";
		$product = $this->make_product( array( $key => 'limited' ) );
		$spec    = $this->spec(
			array(
				$this->group(
					array(
						array(
							'type'    => 'select',
							'id'      => "{$this->prefix}_wrwc_warranty_type",
							'name'    => $key,
							'label'   => 'Warranty type',
							'options' => array(
								'none'     => 'None',
								'limited'  => 'Limited',
								'lifetime' => 'Lifetime',
							),
						),
					)
				),
			)
		);

		$this->save( $spec, $product->get_id() );
		self::assertSame( array( 'limited' ), \get_post_meta( $product->get_id(), $key, false ) );

		$this->save( $spec, $product->get_id(), array( $key => 'lifetime' ) );
		self::assertSame( array( 'lifetime' ), \get_post_meta( $product->get_id(), $key, false ) );
	}

	public function test_saving_the_tabs_fields_adds_no_product_save_to_woocommerces_own(): void {
		$product = $this->make_product();

		$without = $this->save( $this->spec( array(), array( 'count_saves' => true ) ), $product->get_id() );
		$with    = $this->save( $this->spec( array( $this->group( array( $this->field( 'text', 'name' ) ) ) ), array( 'count_saves' => true ) ), $product->get_id(), array( "_{$this->prefix}_name" => 'new' ) );

		self::assertSame( array( 'new' ), $this->meta( $product, 'name' ) );
		self::assertNotSame( array(), $without['records'] );
		self::assertNotContains( true, \array_column( $without['records'], 2 ) );
		self::assertSame( $without['records'], $with['records'] );
	}

	public function test_an_unchanged_save_keeps_the_stored_value_under_a_view_filter(): void {
		$product = $this->make_product( array( "_{$this->prefix}_name" => 'stored' ) );
		$spec    = $this->spec( array( $this->group( array( $this->field( 'text', 'name' ) ) ) ), array( 'filtered' => "_{$this->prefix}_name" ) );

		$this->save( $spec, $product->get_id() );

		self::assertSame( array( 'stored' ), $this->meta( $product, 'name' ) );
	}

	public function test_a_per_product_field_list_receives_the_product_being_saved(): void {
		$product = $this->make_product();
		$spec    = $this->spec( array( $this->group( array( $this->field( 'text', 'name' ) ), array( 'per_product' => true ) ) ) );

		$saved = $this->save( $spec, $product->get_id(), array( "_{$this->prefix}_name" => 'new' ) );

		self::assertSame( array( 'new' ), $this->meta( $product, 'name' ) );
		self::assertSame( array( array( 'fields', $product->get_id() ) ), $saved['records'] );
	}

	public function test_a_group_contributed_through_the_groups_filter_saves_for_the_product_being_saved(): void {
		$product = $this->make_product();
		$spec    = $this->spec( array(), array( 'contributed' => array( $this->field( 'text', 'extra', array( 'validate' => 'acme:append' ) ) ) ) );

		$saved = $this->save( $spec, $product->get_id(), array( "_{$this->prefix}_extra" => 'v' ) );

		self::assertSame( array( 'v!' ), $this->meta( $product, 'extra' ) );
		self::assertSame(
			array(
				array( 'groups', $product->get_id() ),
				array( 'validate', "_{$this->prefix}_extra", 'v' ),
			),
			$saved['records']
		);
	}

	public function test_a_new_product_saves_its_fields_when_published(): void {
		$spec = $this->spec( array( $this->group( array( $this->field( 'text', 'name', array( 'default' => 'fresh' ) ), $this->field( 'checkbox', 'flag' ) ) ) ) );

		$screen     = $this->new_screen( $spec );
		$data       = self::serialize_form(
			$screen['body'],
			'form#post',
			array(
				'post_title'            => 'Acme product',
				"_{$this->prefix}_flag" => true,
			)
		) + array( 'publish' => 'Publish' );
		$product_id = (int) $data['post_ID'];
		$this->admin_request( 'POST', 'post.php', $this->admin, self::FIXTURE, $spec, $data );

		self::assertSame( 'publish', \get_post_status( $product_id ) );
		self::assertSame( array( 'fresh' ), \get_post_meta( $product_id, "_{$this->prefix}_name", false ) );
		self::assertSame( array( 'yes' ), \get_post_meta( $product_id, "_{$this->prefix}_flag", false ) );
	}

	protected function spec( array $groups, array $extra = array() ): array {
		return array(
			'id'     => $this->prefix,
			'label'  => 'Acme',
			'groups' => $groups,
		) + $extra;
	}

	protected function group( array $fields, array $keys = array() ): array {
		return array( 'fields' => $fields ) + $keys;
	}

	protected function field( string $type, string $name, array $keys = array() ): array {
		return $keys + array(
			'type'  => $type,
			'id'    => "_{$this->prefix}_$name",
			'label' => \ucfirst( $name ),
		);
	}

	protected function custom_field( array $keys = array() ): array {
		return $this->field( 'acme_widget', 'widget', array( 'render' => 'acme:render' ) + $keys );
	}

	protected function edit_screen( array $spec, int $product_id ): array {
		return $this->admin_request( 'GET', "post.php?post=$product_id&action=edit", $this->admin, self::FIXTURE, $spec );
	}

	protected function new_screen( array $spec ): array {
		$request = $this->admin_request( 'GET', 'post-new.php?post_type=product', $this->admin, self::FIXTURE, $spec );

		$this->product_ids[] = (int) self::document( $request['body'] )->querySelector( 'input#post_ID' )?->getAttribute( 'value' );

		return $request;
	}

	// The form posts as the Update button submits it, after the user set the named controls; tampering replaces or removes posted keys.
	protected function save( array $spec, int $product_id, array $changes = array(), array $tampered = array(), array $removed = array() ): array {
		$data = \array_replace( self::serialize_form( $this->edit_screen( $spec, $product_id )['body'], 'form#post', $changes ), $tampered ) + array( 'save' => 'Update' );
		foreach ( $removed as $name ) {
			unset( $data[ $name ] );
		}

		return $this->admin_request( 'POST', 'post.php', $this->admin, self::FIXTURE, $spec, $data );
	}

	protected function meta( \WC_Product $product, string $name ): array {
		return \get_post_meta( $product->get_id(), "_{$this->prefix}_$name", false );
	}

	// WooCommerce shows the errors a save stored on the next admin page.
	protected function errors( array $spec, \WC_Product $product ): array {
		return self::texts( self::document( $this->edit_screen( $spec, $product->get_id() )['body'] ), '#woocommerce_errors p' );
	}

	protected static function texts( \Dom\HTMLDocument $document, string $selector ): array {
		$texts = array();
		foreach ( $document->querySelectorAll( $selector ) as $element ) {
			$texts[] = \trim( $element->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		}

		return $texts;
	}

	protected function control( \Dom\HTMLDocument $screen, string $name ): \Dom\Element {
		$control = $screen->querySelector( "[id=\"_{$this->prefix}_$name\"]" );
		self::assertNotNull( $control, "The panel printed no _{$this->prefix}_$name." );

		return $control;
	}

	protected function selected( \Dom\HTMLDocument $screen, string $name ): array {
		$values = array();
		foreach ( $this->control( $screen, $name )->querySelectorAll( 'option[selected]' ) as $option ) {
			$values[] = (string) $option->getAttribute( 'value' );
		}

		return $values;
	}

	protected function checked( ?\Dom\Element $container, string $name ): array {
		$values = array();
		foreach ( $container?->querySelectorAll( "input[type=checkbox][name=\"$name\"][checked]" ) ?? array() as $box ) {
			$values[] = (string) $box->getAttribute( 'value' );
		}

		return $values;
	}

	protected function tab_keys( \Dom\HTMLDocument $screen ): array {
		$keys = array();
		foreach ( $screen->querySelectorAll( 'ul.product_data_tabs > li' ) as $item ) {
			$keys[] = \substr( \explode( ' ', (string) $item->getAttribute( 'class' ) )[0], 0, -\strlen( '_options' ) );
		}

		return $keys;
	}
}
