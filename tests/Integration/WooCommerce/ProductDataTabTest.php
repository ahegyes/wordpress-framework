<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\WooCommerce;

use DeepWebSolutions\Framework\Shared\Exception\InvalidArgumentException;
use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesStorageTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\ProvidesHelperIdsTrait;
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

	protected const string FIXTURE = __DIR__ . '/../../Fixtures/WooCommerce/acme-product-data.php';

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

	#[DataProvider( 'invalid_helper_ids' )]
	public function test_an_invalid_tab_id_is_rejected( string $id ): void {
		$this->expectException( InvalidArgumentException::class );

		new ProductDataTab( $id, static fn (): array => array() );
	}

	#[DataProvider( 'v1_helper_ids' )]
	public function test_every_v1_key_is_a_valid_tab_id( string $id ): void {
		$tab = new ProductDataTab( $id, static fn (): array => array() );
		$tab->register_hooks();

		self::assertSame( 10, \has_filter( 'woocommerce_product_data_tabs', array( $tab, 'add_tab' ) ) );
	}

	public function test_the_definition_is_evaluated_only_when_the_edit_screen_needs_it(): void {
		$calls = 0;
		$tab   = new ProductDataTab(
			$this->prefix,
			static function () use ( &$calls ): array {
				++$calls;

				return array();
			}
		);

		$tab->register_hooks();

		self::assertSame( 0, $calls );
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
		self::assertSame( "_{$this->prefix}_colors[]", $hidden?->nextElementSibling?->getAttribute( 'name' ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		self::assertTrue( $this->control( $screen, 'colors' )->hasAttribute( 'multiple' ) );
		self::assertSame( array( 'red', 'teal' ), $this->selected( $screen, 'colors' ) );
	}

	public function test_an_enabled_checkbox_posts_no_when_unchecked(): void {
		$product = $this->make_product();
		$spec    = $this->spec( array( $this->group( array( $this->field( 'checkbox', 'flag' ) ) ) ) );

		$screen = self::document( $this->edit_screen( $spec, $product->get_id() )['body'] );

		self::assertSame( 'no', $screen->querySelector( "input[type=hidden][name=\"_{$this->prefix}_flag\"]" )?->getAttribute( 'value' ) );
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
		return array(
			'type'  => $type,
			'id'    => "_{$this->prefix}_$name",
			'label' => \ucfirst( $name ),
		) + $keys;
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
