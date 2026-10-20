<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Settings;

use DeepWebSolutions\Framework\Settings\ObjectForm;
use DeepWebSolutions\Framework\Settings\PostMetaBox;
use DeepWebSolutions\Framework\Shared\Exception\InvalidArgumentException;
use DeepWebSolutions\Framework\Shared\Exception\LogicException;
use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesStorageTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\ProvidesHelperIdsTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\RunsAdminRequestsTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\SerializesFormsTrait;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( ObjectForm::class )]
#[CoversClass( PostMetaBox::class )]
final class PostMetaBoxTest extends TestCase {
	use CreatesUsersTrait;
	use IsolatesHooksTrait;
	use IsolatesStorageTrait;
	use ProvidesHelperIdsTrait;
	use RunsAdminRequestsTrait;
	use SerializesFormsTrait;

	protected const string FIXTURE = __DIR__ . '/../../Fixtures/Settings/acme-object-forms.php';

	protected const array COLORS = array(
		'red'  => 'Red',
		'blue' => 'Blue',
		'teal' => 'Teal',
	);

	protected int $admin;
	protected int $editor;
	protected string $id;
	protected array $post_ids = array();

	#[\Override]
	protected function setUp(): void {
		$this->admin  = $this->make_user( 'acme_object_forms_admin', 'administrator' );
		$this->editor = $this->make_user( 'acme_object_forms_editor', 'editor' );
		$this->id     = "{$this->prefix}_details";
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

	#[DataProvider( 'invalid_helper_ids' )]
	public function test_an_invalid_form_id_is_rejected( string $id ): void {
		$this->expectException( InvalidArgumentException::class );

		new ObjectForm( $id, static fn (): array => array(), new PostMetaBox( array( 'post' ) ) );
	}

	#[DataProvider( 'v1_helper_ids' )]
	public function test_every_v1_key_is_a_valid_form_id( string $id ): void {
		$host = new PostMetaBox( array( 'post' ) );
		$form = new ObjectForm( $id, static fn (): array => array(), $host );
		$form->register_hooks();

		self::assertSame( 10, \has_action( 'all_admin_notices', array( $form, 'render_rejections' ) ) );
		self::assertSame( 10, \has_action( 'add_meta_boxes', array( $host, 'add_meta_box' ) ) );
	}

	public function test_a_host_serves_one_form(): void {
		$host = new PostMetaBox( array( 'post' ) );
		( new ObjectForm( "{$this->id}_a", static fn (): array => array(), $host ) )->register_hooks();

		$this->expectException( LogicException::class );

		( new ObjectForm( "{$this->id}_b", static fn (): array => array(), $host ) )->register_hooks();
	}

	public function test_the_definition_is_evaluated_only_when_a_screen_needs_it(): void {
		$calls = 0;
		$form  = new ObjectForm(
			$this->id,
			static function () use ( &$calls ): array {
				++$calls;

				return array();
			},
			new PostMetaBox( array( 'post' ) )
		);
		$form->register_hooks();
		$post_id = $this->make_post();
		$spec    = $this->spec( array( $this->field( 'text', 'name' ) ) );

		$dashboard = $this->admin_request( 'GET', 'index.php', $this->admin, self::FIXTURE, $spec );
		$edit      = $this->edit_screen( $spec, $post_id );

		self::assertSame( 0, $calls );
		self::assertNotContains( array( 'definition' ), $dashboard['records'] );
		self::assertSame( array( array( 'definition' ) ), \array_values( \array_filter( $edit['records'], static fn ( array $record ): bool => 'definition' === $record[0] ) ) );
	}

	public function test_the_box_shows_on_the_edit_screens_of_its_post_types_only(): void {
		$post_id = $this->make_post();
		$page_id = $this->make_post( array(), 'page' );
		$spec    = $this->spec( array( $this->field( 'text', 'name' ) ) );

		$post = self::document( $this->edit_screen( $spec, $post_id )['body'] );
		$page = self::document( $this->edit_screen( $spec, $page_id )['body'] );

		self::assertSame( 'Acme details', \trim( (string) $post->querySelector( "form#post div.postbox[id=\"{$this->id}\"] h2.hndle" )?->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		self::assertNotNull( $this->box( $post )->querySelector( "input[type=hidden][name=\"{$this->id}_nonce\"]" ) );
		self::assertNull( $page->querySelector( "div.postbox[id=\"{$this->id}\"]" ) );
	}

	public function test_the_box_sits_in_its_context(): void {
		$post_id = $this->make_post();
		$spec    = $this->spec(
			array( $this->field( 'text', 'name' ) ),
			array(),
			array(
				'host_args' => array(
					'post_types' => array( 'post' ),
					'context'    => 'side',
				),
			)
		);

		$screen = self::document( $this->edit_screen( $spec, $post_id )['body'] );

		self::assertNotNull( $screen->querySelector( "#side-sortables div.postbox[id=\"{$this->id}\"]" ) );
	}

	public function test_a_user_lacking_the_forms_capability_gets_no_box(): void {
		$post_id = $this->make_post();
		$spec    = $this->spec( array( $this->field( 'text', 'name' ) ), array( 'capability' => 'manage_options' ) );

		$screen = self::document( $this->edit_screen( $spec, $post_id, $this->editor )['body'] );

		self::assertNotNull( $screen->querySelector( 'form#post' ) );
		self::assertNull( $screen->querySelector( "div.postbox[id=\"{$this->id}\"]" ) );
	}

	public function test_a_single_control_is_labelled_by_its_row(): void {
		$post_id = $this->make_post();
		$spec    = $this->spec( array( $this->field( 'text', 'name' ) ) );

		$box = $this->box( self::document( $this->edit_screen( $spec, $post_id )['body'] ) );

		$label = $box->querySelector( "div.form-field > p.post-attributes-label-wrapper > label.post-attributes-label[for=\"{$this->id}._{$this->prefix}_name\"]" );
		self::assertNotNull( $label );
		self::assertSame( 'Name', \trim( $label->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		self::assertNotNull( $box->querySelector( "div.form-field > input[type=text][id=\"{$this->id}._{$this->prefix}_name\"][name=\"{$this->id}[_{$this->prefix}_name]\"]" ) );
	}

	#[DataProvider( 'group_fields' )]
	public function test_a_group_is_named_by_its_rows_visible_legend( array $keys ): void {
		$post_id = $this->make_post();
		$spec    = $this->spec( array( $this->field( $keys['type'], 'color', $keys + array( 'options' => self::COLORS ) ) ) );

		$box = $this->box( self::document( $this->edit_screen( $spec, $post_id )['body'] ) );

		$row = $box->querySelector( 'fieldset.form-field' );
		self::assertNotNull( $row );
		self::assertSame( 'Color', \trim( (string) $row->querySelector( ':scope > legend.post-attributes-label' )?->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		self::assertCount( 1, $row->querySelectorAll( 'legend' ) );
		self::assertNull( $box->querySelector( "label.post-attributes-label[for=\"{$this->id}._{$this->prefix}_color\"]" ) );
		self::assertNotNull( $row->querySelector( "input[name^=\"{$this->id}[_{$this->prefix}_color]\"]" ) );
	}

	public function test_controls_carry_the_stored_value_or_else_the_default(): void {
		$post_id = $this->make_post(
			array(
				"_{$this->prefix}_name"  => 'stored',
				"_{$this->prefix}_empty" => '',
				"_{$this->prefix}_list"  => array(),
			)
		);
		$spec    = $this->spec(
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
				$this->field( 'checkbox', 'flag', array( 'default' => 'yes' ) ),
			)
		);

		$box = $this->box( self::document( $this->edit_screen( $spec, $post_id )['body'] ) );

		self::assertSame( 'stored', $this->control( $box, 'name' )->getAttribute( 'value' ) );
		self::assertSame( '', $this->control( $box, 'empty' )->getAttribute( 'value' ) );
		self::assertSame( array(), $this->selected( $box, 'list' ) );
		self::assertSame( array( 'blue' ), $this->selected( $box, 'color' ) );
		self::assertTrue( $this->control( $box, 'flag' )->hasAttribute( 'checked' ) );
	}

	public function test_per_object_fields_receive_the_post_and_contributed_fields_render(): void {
		$post_id = $this->make_post();
		$spec    = $this->spec(
			array( $this->field( 'text', 'name' ) ),
			array(),
			array(
				'per_object'  => true,
				'contributed' => array( $this->field( 'text', 'extra' ) ),
			)
		);

		$request = $this->edit_screen( $spec, $post_id );
		$box     = $this->box( self::document( $request['body'] ) );

		self::assertContains( array( 'fields', $post_id ), $request['records'] );
		self::assertContains( array( 'contributed', $post_id ), $request['records'] );
		$this->control( $box, 'name' );
		$this->control( $box, 'extra' );
	}

	public function test_a_custom_control_renders_with_its_value_name_and_id(): void {
		$post_id = $this->make_post( array( "_{$this->prefix}_widget" => 'stored' ) );
		$spec    = $this->spec(
			array(
				$this->field(
					'acme_widget',
					'widget',
					array(
						'render'   => 'acme:render',
						'validate' => 'acme:record',
					)
				),
			)
		);

		$request = $this->edit_screen( $spec, $post_id );
		$box     = $this->box( self::document( $request['body'] ) );

		self::assertContains( array( 'render', "_{$this->prefix}_widget", 'stored', "{$this->id}[_{$this->prefix}_widget]", "{$this->id}._{$this->prefix}_widget" ), $request['records'] );
		self::assertNotNull( $box->querySelector( "label[for=\"{$this->id}._{$this->prefix}_widget\"]" ) );
		self::assertNotNull( $box->querySelector( "input.acme-widget[id=\"{$this->id}._{$this->prefix}_widget\"]" ) );
	}

	public function test_titles_print_as_text(): void {
		$post_id = $this->make_post();
		$spec    = $this->spec( array( $this->field( 'text', 'note', array( 'title' => 'Customer\'s <b>note</b>' ) ) ) );

		$label = $this->box( self::document( $this->edit_screen( $spec, $post_id )['body'] ) )->querySelector( "label[for=\"{$this->id}._{$this->prefix}_note\"]" );

		self::assertSame( 'Customer\'s <b>note</b>', \trim( (string) $label?->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		self::assertNull( $label?->querySelector( 'b' ) );
	}

	public function test_an_untitled_field_is_labelled_by_its_id(): void {
		$post_id = $this->make_post();
		$spec    = $this->spec( array( $this->field( 'text', 'name', array( 'title' => '' ) ) ) );

		$label = $this->box( self::document( $this->edit_screen( $spec, $post_id )['body'] ) )->querySelector( "label[for=\"{$this->id}._{$this->prefix}_name\"]" );

		self::assertSame( "_{$this->prefix}_name", \trim( (string) $label?->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
	}

	public function test_fields_without_an_id_or_a_control_print_no_row(): void {
		$post_id = $this->make_post();
		$spec    = $this->spec(
			array(
				$this->field( 'text', 'name' ),
				array(
					'type'  => 'text',
					'title' => 'Anonymous',
				),
				$this->field( 'acme_unknown', 'unknown' ),
			)
		);

		$box = $this->box( self::document( $this->edit_screen( $spec, $post_id )['body'] ) );

		self::assertCount( 1, $box->querySelectorAll( '.form-field' ) );
	}

	protected function spec( array $fields, array $form = array(), array $extra = array() ): array {
		return $extra + array(
			'id'        => $this->id,
			'host'      => 'post',
			'host_args' => array( 'post_types' => array( 'post' ) ),
			'editor'    => 'classic',
			'form'      => $form + array(
				'title'      => 'Acme details',
				'capability' => 'edit_posts',
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

	protected function make_post( array $meta = array(), string $type = 'post', string $status = 'publish' ): int {
		$post_id = \wp_insert_post(
			array(
				'post_type'   => $type,
				'post_title'  => 'Acme post',
				'post_status' => $status,
				'post_author' => $this->admin,
			)
		);
		self::assertIsInt( $post_id );
		foreach ( $meta as $key => $value ) {
			\add_post_meta( $post_id, $key, \wp_slash( $value ) );
		}
		$this->post_ids[] = $post_id;

		return $post_id;
	}

	protected function edit_screen( array $spec, int $post_id, ?int $user_id = null ): array {
		return $this->admin_request( 'GET', "post.php?post=$post_id&action=edit", $user_id ?? $this->admin, self::FIXTURE, $spec );
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

	#[After]
	protected function delete_posts(): void {
		foreach ( $this->post_ids as $post_id ) {
			\wp_delete_post( $post_id, true );
		}
	}
}
