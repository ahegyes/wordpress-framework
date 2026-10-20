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

	public static function silent_and_worded_rejections(): array {
		return array(
			'worded' => array( 'acme:reject', "Customer's <b>note</b> \\o/ was not saved, so it keeps its previous value. Use at most four characters." ),
			'silent' => array( 'acme:reject-silently', "Customer's <b>note</b> \\o/ was not saved because its value is not valid, so it keeps its previous value." ),
		);
	}

	public function test_a_box_serves_one_form(): void {
		$host = new OrderMetaBox();
		( new ObjectForm( "{$this->id}_a", static fn (): array => array(), $host ) )->register_hooks();

		$this->expectException( LogicException::class );

		( new ObjectForm( "{$this->id}_b", static fn (): array => array(), $host ) )->register_hooks();
	}

	public function test_the_box_is_added_on_add_meta_boxes(): void {
		$host = new OrderMetaBox();
		( new ObjectForm( $this->id, static fn (): array => array(), $host ) )->register_hooks();

		self::assertSame( 10, \has_action( 'add_meta_boxes', array( $host, 'add_meta_box' ) ) );
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
		$elsewhere = self::document( $this->edit_screen( $other, $order->get_id() )['body'] );

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

	public function test_the_box_saves_before_woocommerces_order_data(): void {
		$host = new OrderMetaBox();
		( new ObjectForm( $this->id, static fn (): array => array(), $host ) )->register_hooks();

		self::assertSame( 35, \has_action( 'woocommerce_process_shop_order_meta', array( $host, 'save_meta_box' ) ) );
	}

	public function test_update_stores_the_accepted_values_through_the_orders_meta(): void {
		$order = $this->make_order(
			array(
				"_{$this->prefix}_kept" => 'kept',
				"_{$this->prefix}_flag" => 'yes',
				"_{$this->prefix}_list" => array( 'red' ),
			)
		);
		$spec  = $this->spec(
			array(
				$this->field( 'text', 'name' ),
				$this->field( 'text', 'kept' ),
				$this->field( 'checkbox', 'flag' ),
				$this->field( 'multiselect', 'list', array( 'options' => self::COLORS ) ),
			)
		);

		$this->save(
			$spec,
			$order->get_id(),
			array(
				$this->control_name( 'name' ) => 'O\'Brien C:\\dir',
				$this->control_name( 'kept' ) => 'unposted',
				$this->control_name( 'flag' ) => false,
				$this->control_name( 'list' ) => array(),
			),
			array(),
			array( array( $this->id, "_{$this->prefix}_kept" ) )
		);

		self::assertSame( array( 'O\'Brien C:\\dir' ), $this->meta( $order->get_id(), 'name' ) );
		self::assertSame( array( 'kept' ), $this->meta( $order->get_id(), 'kept' ) );
		self::assertSame( array( 'no' ), $this->meta( $order->get_id(), 'flag' ) );
		self::assertSame( array( array() ), $this->meta( $order->get_id(), 'list' ) );
	}

	#[DataProvider( 'silent_and_worded_rejections' )]
	public function test_a_rejection_keeps_the_value_and_shows_once_on_the_edit_screen_woocommerce_redirects_to( string $behavior, string $message ): void {
		$order = $this->make_order( array( "_{$this->prefix}_note" => 'kept' ) );
		$spec  = $this->spec(
			array(
				$this->field(
					'text',
					'note',
					array(
						'title'    => 'Customer\'s <b>note</b> \\o/',
						'validate' => $behavior,
					)
				),
			)
		);

		$saved = $this->save( $spec, $order->get_id(), array( $this->control_name( 'note' ) => 'too long' ) );
		$next  = $this->follow( $spec, $saved );
		$after = $this->edit_screen( $spec, $order->get_id() );

		self::assertStringContainsString( "page=wc-orders&action=edit&id={$order->get_id()}", (string) $saved['location'] );
		self::assertSame( array( 'kept' ), $this->meta( $order->get_id(), 'note' ) );
		self::assertSame( array( $message ), self::rejections( $next['body'] ) );
		self::assertSame( array(), self::rejections( $after['body'] ) );
	}

	public function test_a_status_change_in_the_same_save_sees_the_stored_values(): void {
		$order = $this->make_order( array( "_{$this->prefix}_name" => 'old' ) );
		$spec  = $this->spec( array( $this->field( 'text', 'name' ) ), array(), array( 'status_probe' => "_{$this->prefix}_name" ) );

		$saved = $this->save(
			$spec,
			$order->get_id(),
			array(
				$this->control_name( 'name' ) => 'new',
				'order_status'                => 'wc-completed',
			)
		);

		self::assertSame( array( array( 'status_changed', 'completed', 'new' ) ), $this->records( $saved, 'status_changed' ) );
	}

	public function test_the_new_order_screen_renders_defaults_and_create_stores_them(): void {
		$spec = $this->spec( array( $this->field( 'text', 'name', array( 'default' => 'fresh' ) ), $this->field( 'checkbox', 'flag' ) ) );

		$screen   = $this->admin_request( 'GET', 'admin.php?page=wc-orders&action=new', $this->admin, self::FIXTURE, $spec );
		$data     = self::serialize_form( $screen['body'], 'form#order', array( $this->control_name( 'flag' ) => true ) );
		$order_id = (int) $data['post_ID'];
		$order    = \wc_get_order( $order_id );
		self::assertInstanceOf( \WC_Order::class, $order );
		$this->orders[] = $order;

		$this->admin_request( 'POST', "admin.php?page=wc-orders&action=edit&id=$order_id", $this->admin, self::FIXTURE, $spec, $data );

		self::assertSame( 'fresh', $this->control( $this->box( self::document( $screen['body'] ) ), 'name' )->getAttribute( 'value' ) );
		self::assertSame( array( 'fresh' ), $this->meta( $order_id, 'name' ) );
		self::assertSame( array( 'yes' ), $this->meta( $order_id, 'flag' ) );
	}

	public function test_a_nonce_minted_for_another_order_or_none_stores_nothing(): void {
		$order = $this->make_order( array( "_{$this->prefix}_name" => 'kept' ) );
		$spec  = $this->spec( array( $this->field( 'text', 'name' ) ) );
		$other = self::serialize_form( $this->edit_screen( $spec, $this->make_order()->get_id() )['body'], 'form#order' );

		$this->save( $spec, $order->get_id(), array( $this->control_name( 'name' ) => 'borrowed' ), array( "{$this->id}_nonce" => $other[ "{$this->id}_nonce" ] ) );
		$this->save( $spec, $order->get_id(), array( $this->control_name( 'name' ) => 'unsigned' ), array(), array( "{$this->id}_nonce" ) );

		self::assertSame( array( 'kept' ), $this->meta( $order->get_id(), 'name' ) );
	}

	public function test_a_user_without_the_forms_capability_stores_nothing(): void {
		$order   = $this->make_order( array( "_{$this->prefix}_name" => 'kept' ) );
		$manager = $this->make_user( 'acme_order_meta_box_manager', 'shop_manager' );
		$open    = $this->spec( array( $this->field( 'text', 'name' ) ) );
		$closed  = $this->spec( array( $this->field( 'text', 'name' ) ), array( 'capability' => 'manage_options' ) );

		$data = self::serialize_form( $this->edit_screen( $open, $order->get_id(), $manager )['body'], 'form#order', array( $this->control_name( 'name' ) => 'forged' ) );
		$this->admin_request( 'POST', "admin.php?page=wc-orders&action=edit&id={$order->get_id()}", $manager, self::FIXTURE, $closed, $data );

		self::assertSame( array( 'kept' ), $this->meta( $order->get_id(), 'name' ) );
	}

	public function test_an_order_saved_in_code_stores_nothing(): void {
		$order = $this->make_order( array( "_{$this->prefix}_name" => 'kept' ) );
		$form  = new ObjectForm(
			$this->id,
			fn (): array => array(
				'title'      => 'Acme details',
				'capability' => 'edit_shop_orders',
				'fields'     => array( $this->field( 'text', 'name' ) ),
			),
			new OrderMetaBox()
		);
		$form->register_hooks();
		\wp_set_current_user( $this->admin );
		$_POST[ "{$this->id}_nonce" ] = \wp_create_nonce( "{$this->id}_nonce_{$order->get_id()}" );
		$_POST[ $this->id ]           = array( "_{$this->prefix}_name" => 'programmatic' );

		try {
			$order->set_customer_note( 'Updated in code' );
			$order->save();
		} finally {
			unset( $_POST[ "{$this->id}_nonce" ], $_POST[ $this->id ] );
			\wp_set_current_user( 0 );
		}

		self::assertSame( 'Updated in code', \wc_get_order( $order->get_id() )->get_customer_note() );
		self::assertSame( array( 'kept' ), $this->meta( $order->get_id(), 'name' ) );
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

	protected function control_name( string $name ): string {
		return "{$this->id}[_{$this->prefix}_$name]";
	}

	// The form posts as WooCommerce's Update button submits it, after the user set the named controls; tampering replaces posted keys and removes posted keys or a key of the form's array.
	protected function save( array $spec, int $order_id, array $changes = array(), array $tampered = array(), array $removed = array() ): array {
		$data = \array_replace_recursive( self::serialize_form( $this->edit_screen( $spec, $order_id )['body'], 'form#order', $changes ), $tampered );
		foreach ( $removed as $name ) {
			if ( \is_array( $name ) ) {
				self::assertArrayHasKey( $name[1], $data[ $name[0] ] );
				unset( $data[ $name[0] ][ $name[1] ] );
			} else {
				unset( $data[ $name ] );
			}
		}

		return $this->admin_request( 'POST', "admin.php?page=wc-orders&action=edit&id=$order_id", $this->admin, self::FIXTURE, $spec, $data );
	}

	protected function follow( array $spec, array $saved ): array {
		self::assertIsString( $saved['location'] );

		return $this->admin_request( 'GET', \explode( '/wp-admin/', $saved['location'], 2 )[1], $this->admin, self::FIXTURE, $spec );
	}

	protected function meta( int $order_id, string $name ): array {
		return $this->order_meta( $order_id, "_{$this->prefix}_$name" );
	}

	protected static function rejections( string $html ): array {
		$messages = array();
		foreach ( self::document( $html )->querySelectorAll( 'div.notice-error.is-dismissible > p' ) as $message ) {
			$messages[] = \trim( $message->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		}

		return $messages;
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
