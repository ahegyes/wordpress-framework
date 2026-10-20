<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\WooCommerce;

use DeepWebSolutions\Framework\Settings\ObjectForm;
use DeepWebSolutions\Framework\Shared\Exception\LogicException;
use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesStorageTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\RunsAdminRequestsTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\SerializesFormsTrait;
use DeepWebSolutions\Framework\WooCommerce\Orders\OrderMetaBox;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( OrderMetaBox::class )]
final class OrderMetaBoxTest extends TestCase {
	use CreatesUsersTrait;
	use IsolatesHooksTrait;
	use IsolatesStorageTrait;
	use OrderFixturesTrait;
	use RunsAdminRequestsTrait;
	use SerializesFormsTrait;

	protected const string FIXTURE = __DIR__ . '/../../Fixtures/Settings/acme-object-forms.php';

	protected const array COLORS = array(
		'red'  => 'Red',
		'blue' => 'Blue',
		'teal' => 'Teal',
	);

	protected int $admin;
	protected string $id;

	#[\Override]
	protected function setUp(): void {
		$this->admin = $this->make_user( 'acme_order_meta_box_admin', 'administrator' );
		$this->id    = "{$this->prefix}_details";
	}

	public static function group_fields(): array {
		return array(
			'radio'              => array( array( 'type' => 'radio' ) ),
			'checkboxes'         => array( array( 'type' => 'checkboxes' ) ),
			'checkbox-with-desc' => array(
				array(
					'type' => 'checkbox',
					'desc' => 'Enable it',
				),
			),
		);
	}

	public function test_a_box_serves_one_form(): void {
		$host = new OrderMetaBox();
		( new ObjectForm( "{$this->id}_a", static fn (): array => array(), $host ) )->register_hooks();

		$this->expectException( LogicException::class );

		( new ObjectForm( "{$this->id}_b", static fn (): array => array(), $host ) )->register_hooks();
	}

	public function test_the_box_is_added_on_add_meta_boxes_and_saves_before_woocommerces_order_data(): void {
		$host = new OrderMetaBox();
		( new ObjectForm( $this->id, static fn (): array => array(), $host ) )->register_hooks();

		self::assertSame( 10, \has_action( 'add_meta_boxes', array( $host, 'add_meta_box' ) ) );
		self::assertSame( 35, \has_action( 'woocommerce_process_shop_order_meta', array( $host, 'save_meta_box' ) ) );
	}

	public function test_the_definition_is_evaluated_only_when_a_screen_needs_it(): void {
		$order = $this->make_order();
		$spec  = $this->spec( array( $this->field( 'text', 'name' ) ) );

		$dashboard = $this->admin_request( 'GET', 'index.php', $this->admin, self::FIXTURE, $spec );
		$edit      = $this->edit_screen( $spec, $order->get_id() );

		self::assertSame( array(), $this->records( $dashboard, 'definition' ) );
		self::assertSame( array( array( 'definition' ) ), $this->records( $edit, 'definition' ) );
	}

	public function test_the_box_shows_on_the_edit_screens_of_its_order_types_only(): void {
		$order = $this->make_order();
		$spec  = $this->spec( array( $this->field( 'text', 'name' ) ) );
		$other = $this->spec( array( $this->field( 'text', 'name' ) ), array(), array( 'host_args' => array( 'order_types' => array( 'shop_subscription' ) ) ) );

		$screen    = self::document( $this->edit_screen( $spec, $order->get_id() )['body'] );
		$elsewhere =self::document( $this->edit_screen( $other, $order->get_id() )['body'] );

		self::assertSame( 'Acme details', \trim( (string) $screen->querySelector( "form#order div.postbox[id=\"{$this->id}\"] h2.hndle" )?->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		self::assertNotNull( $this->box( $screen )->querySelector( "input[type=hidden][name=\"{$this->id}_nonce\"]" ) );
		self::assertNotNull( $elsewhere->querySelector( 'form#order' ) );
		self::assertNull( $elsewhere->querySelector( "div.postbox[id=\"{$this->id}\"]" ) );
	}

	public function test_the_box_sits_in_its_context(): void {
		$order = $this->make_order();
		$spec  = $this->spec(
			array( $this->field( 'text', 'name' ) ),
			array(),
			array(
				'host_args' => array(
					'order_types' => array( 'shop_order' ),
					'context'     => 'side',
				),
			)
		);

		$screen = self::document( $this->edit_screen( $spec, $order->get_id() )['body'] );

		self::assertNotNull( $screen->querySelector( "#side-sortables div.postbox[id=\"{$this->id}\"]" ) );
	}

	public function test_a_user_lacking_the_forms_capability_gets_no_box(): void {
		$order   = $this->make_order();
		$manager = $this->make_user( 'acme_order_meta_box_manager', 'shop_manager' );
		$spec    = $this->spec( array( $this->field( 'text', 'name' ) ), array( 'capability' => 'manage_options' ) );

		$screen = self::document( $this->edit_screen( $spec, $order->get_id(), $manager )['body'] );

		self::assertNotNull( $screen->querySelector( 'form#order' ) );
		self::assertNull( $screen->querySelector( "div.postbox[id=\"{$this->id}\"]" ) );
	}

	public function test_a_single_control_is_labelled_by_its_row(): void {
		$order = $this->make_order();
		$spec  = $this->spec( array( $this->field( 'text', 'name' ) ) );

		$box = $this->box( self::document( $this->edit_screen( $spec, $order->get_id() )['body'] ) );

		$label = $box->querySelector( "div.form-field > p.post-attributes-label-wrapper > label.post-attributes-label[for=\"{$this->id}._{$this->prefix}_name\"]" );
		self::assertNotNull( $label );
		self::assertSame( 'Name', \trim( $label->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		self::assertNotNull( $box->querySelector( "div.form-field > input[type=text][id=\"{$this->id}._{$this->prefix}_name\"][name=\"{$this->id}[_{$this->prefix}_name]\"]" ) );
	}

	#[DataProvider( 'group_fields' )]
	public function test_a_group_is_named_by_its_rows_visible_legend( array $keys ): void {
		$order = $this->make_order();
		$spec  = $this->spec( array( $this->field( $keys['type'], 'color', $keys + array( 'options' => self::COLORS ) ) ) );

		$box = $this->box( self::document( $this->edit_screen( $spec, $order->get_id() )['body'] ) );

		$row = $box->querySelector( 'fieldset.form-field' );
		self::assertNotNull( $row );
		self::assertSame( 'Color', \trim( (string) $box->querySelector( 'fieldset.form-field > legend.post-attributes-label' )?->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		self::assertCount( 1, $row->querySelectorAll( 'legend' ) );
		self::assertNull( $box->querySelector( "label.post-attributes-label[for=\"{$this->id}._{$this->prefix}_color\"]" ) );
		self::assertNotNull( $row->querySelector( "input[name^=\"{$this->id}[_{$this->prefix}_color]\"]" ) );
	}

	public function test_controls_carry_the_stored_value_or_else_the_default(): void {
		$order = $this->make_order(
			array(
				"_{$this->prefix}_name"  => 'stored',
				"_{$this->prefix}_empty" => '',
				"_{$this->prefix}_list"  => array(),
			)
		);
		$spec  = $this->spec(
			array(
				$this->field( 'text', 'name', array( 'default' => 'fresh' ) ),
				$this->field( 'text', 'empty', array( 'default' => 'fresh' ) ),
				$this->field(
					'multiselect',
					'list',
					array(
						'options' => self::COLORS,
						'default' => array( 'red' ),
					)
				),
				$this->field(
					'select',
					'color',
					array(
						'options' => self::COLORS,
						'default' => 'blue',
					)
				),
			)
		);

		$box = $this->box( self::document( $this->edit_screen( $spec, $order->get_id() )['body'] ) );

		self::assertSame( 'stored', $this->control( $box, 'name' )->getAttribute( 'value' ) );
		self::assertSame( '', $this->control( $box, 'empty' )->getAttribute( 'value' ) );
		self::assertSame( array(), $this->selected( $box, 'list' ) );
		self::assertSame( array( 'blue' ), $this->selected( $box, 'color' ) );
	}

	public function test_per_object_fields_receive_the_order_and_contributed_fields_render(): void {
		$order = $this->make_order();
		$spec  = $this->spec(
			array( $this->field( 'text', 'name' ) ),
			array(),
			array(
				'per_object'  => true,
				'contributed' => array( $this->field( 'text', 'extra' ) ),
			)
		);

		$request = $this->edit_screen( $spec, $order->get_id() );
		$box     = $this->box( self::document( $request['body'] ) );

		self::assertContains( array( 'fields', $order->get_id() ), $request['records'] );
		self::assertContains( array( 'contributed', $order->get_id() ), $request['records'] );
		$this->control( $box, 'name' );
		$this->control( $box, 'extra' );
	}

	protected function spec( array $fields, array $form = array(), array $extra = array() ): array {
		return $extra + array(
			'id'        => $this->id,
			'host'      => 'order',
			'host_args' => array( 'order_types' => array( 'shop_order' ) ),
			'form'      => $form + array(
				'title'      => 'Acme details',
				'capability' => 'edit_shop_orders',
				'fields'     => $fields,
			),
		);
	}

	protected function field( string $type, string $name, array $keys = array() ): array {
		return $keys + array(
			'type'  => $type,
			'id'    => "_{$this->prefix}_$name",
			'title' => \ucfirst( $name ),
		);
	}

	protected function records( array $request, string $kind ): array {
		return \array_values( \array_filter( $request['records'], static fn ( array $record ): bool => $kind === $record[0] ) );
	}

	protected function edit_screen( array $spec, int $order_id, ?int $user_id = null ): array {
		return $this->admin_request( 'GET', "admin.php?page=wc-orders&action=edit&id=$order_id", $user_id ?? $this->admin, self::FIXTURE, $spec );
	}

	protected function box( \Dom\HTMLDocument $screen ): \Dom\Element {
		$box = $screen->querySelector( "div.postbox[id=\"{$this->id}\"] div.inside" );
		self::assertNotNull( $box, "The screen printed no {$this->id} box." );

		return $box;
	}

	protected function control( \Dom\Element $box, string $name ): \Dom\Element {
		$control = $box->querySelector( "[id=\"{$this->id}._{$this->prefix}_$name\"]" );
		self::assertNotNull( $control, "The box printed no _{$this->prefix}_$name." );

		return $control;
	}

	protected function selected( \Dom\Element $box, string $name ): array {
		$values = array();
		foreach ( $this->control( $box, $name )->querySelectorAll( 'option[selected]' ) as $option ) {
			$values[] = (string) $option->getAttribute( 'value' );
		}

		return $values;
	}
}
