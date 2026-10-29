<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Settings\EditScreen\Hosts;

use DeepWebSolutions\Framework\Core\AdminNotice\AdminNotices;
use DeepWebSolutions\Framework\Core\Logging\ErrorLogLogger;
use DeepWebSolutions\Framework\Settings\EditScreen\Hosts\PostMetaBox;
use DeepWebSolutions\Framework\Settings\EditScreen\ObjectForm;
use DeepWebSolutions\Framework\Shared\Exception\InvalidArgumentException;
use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesStorageTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\ProvidesHelperIdsTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\RecordedDefinition;
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

	protected const string FIXTURE = __DIR__ . '/../../../../Fixtures/Settings/acme-object-forms.php';

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

	public static function silent_and_worded_rejections(): array {
		return array(
			'worded' => array( 'acme:reject', "Customer's <b>note</b> \\o/ was not saved, so it keeps its previous value. Use at most four characters." ),
			'silent' => array( 'acme:reject-silently', "Customer's <b>note</b> \\o/ was not saved because its value is not valid, so it keeps its previous value." ),
		);
	}

	public static function heartbeat_refreshes(): array {
		return array(
			'classic-editor' => array( 'wp-refresh-post-nonces' ),
			'block-editor'   => array( 'wp-refresh-metabox-loader-nonces' ),
		);
	}

	#[DataProvider( 'invalid_helper_ids' )]
	public function test_an_invalid_form_id_is_rejected( string $id ): void {
		$this->expectException( InvalidArgumentException::class );

		new ObjectForm( $id, new RecordedDefinition( static fn (): array => array() ), new PostMetaBox( array( 'post' ) ), new AdminNotices( $this->prefix, new ErrorLogLogger( 'acme' ) ) );
	}

	#[DataProvider( 'v1_helper_ids' )]
	public function test_every_v1_key_is_a_valid_form_id( string $id ): void {
		$host = new PostMetaBox( array( 'post' ) );
		$form = new ObjectForm( $id, new RecordedDefinition( static fn (): array => array() ), $host, new AdminNotices( $this->prefix, new ErrorLogLogger( 'acme' ) ) );
		$form->register_hooks();

		self::assertSame( 10, has_action( 'add_meta_boxes', array( $host, 'add_meta_box' ) ) );
	}

	public function test_the_definition_is_evaluated_only_when_a_screen_needs_it(): void {
		$definition = new RecordedDefinition( static fn (): array => array() );
		$form       = new ObjectForm( $this->id, $definition, new PostMetaBox( array( 'post' ) ), new AdminNotices( $this->prefix, new ErrorLogLogger( 'acme' ) ) );
		$form->register_hooks();
		$post_id = $this->make_post();
		$spec    = $this->spec( array( $this->field( 'text', 'name' ) ) );

		$dashboard = $this->admin_request( 'GET', 'index.php', $this->admin, self::FIXTURE, $spec );
		$edit      = $this->edit_screen( $spec, $post_id );

		self::assertSame( array(), $definition->calls );
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
	public function test_a_group_is_one_fieldset_named_by_its_own_legend_under_its_rows_title( array $keys ): void {
		$post_id = $this->make_post();
		$spec    = $this->spec( array( $this->field( $keys['type'], 'color', $keys + array( 'options' => self::COLORS ) ) ) );

		$box = $this->box( self::document( $this->edit_screen( $spec, $post_id )['body'] ) );

		$fieldset = $box->querySelector( 'div.form-field > fieldset' );
		self::assertNotNull( $fieldset );
		self::assertCount( 1, $box->querySelectorAll( 'fieldset' ) );
		self::assertSame( 'Color', \trim( (string) $fieldset->querySelector( 'legend.screen-reader-text' )?->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		self::assertSame( 'Color', \trim( (string) $fieldset->parentElement?->querySelector( 'p.post-attributes-label-wrapper > span.post-attributes-label' )?->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		self::assertNull( $box->querySelector( "label.post-attributes-label[for=\"{$this->id}._{$this->prefix}_color\"]" ) );
		self::assertNotNull( $fieldset->querySelector( "input[name^=\"{$this->id}[_{$this->prefix}_color]\"]" ) );
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

	public function test_update_stores_the_accepted_values_under_their_field_ids(): void {
		$post_id = $this->make_post(
			array(
				"_{$this->prefix}_flag"  => 'yes',
				"_{$this->prefix}_boxes" => array( 'red' ),
			)
		);
		$spec    = $this->spec(
			array(
				$this->field( 'text', 'name' ),
				$this->field( 'text', 'shout', array( 'validate' => 'acme:append' ) ),
				$this->field( 'checkbox', 'flag' ),
				$this->field( 'multiselect', 'list', array( 'options' => self::COLORS ) ),
				$this->field( 'checkboxes', 'boxes', array( 'options' => self::COLORS ) ),
			)
		);

		$this->save(
			$spec,
			$post_id,
			array(
				$this->control_name( 'name' )  => 'O\'Brien C:\\dir',
				$this->control_name( 'shout' ) => 'v',
				$this->control_name( 'flag' )  => false,
				$this->control_name( 'list' )  => array( 'red', 'teal' ),
				$this->control_name( 'boxes' ) => array(),
			)
		);

		self::assertSame( array( 'O\'Brien C:\\dir' ), $this->meta( $post_id, 'name' ) );
		self::assertSame( array( 'v!' ), $this->meta( $post_id, 'shout' ) );
		self::assertSame( array( 'no' ), $this->meta( $post_id, 'flag' ) );
		self::assertSame( array( array( 'red', 'teal' ) ), $this->meta( $post_id, 'list' ) );
		self::assertSame( array( array() ), $this->meta( $post_id, 'boxes' ) );
	}

	public function test_an_attachments_edit_screen_stores_the_accepted_values(): void {
		$attachment_id = $this->make_post( array(), 'attachment', 'inherit' );
		$spec          = $this->spec( array( $this->field( 'text', 'name' ) ), array(), array( 'host_args' => array( 'post_types' => array( 'attachment' ) ) ) );

		$this->save( $spec, $attachment_id, array( $this->control_name( 'name' ) => 'Acme' ) );

		self::assertSame( array( 'Acme' ), $this->meta( $attachment_id, 'name' ) );
	}

	public function test_validate_receives_the_unslashed_submission_alone(): void {
		$post_id = $this->make_post();
		$spec    = $this->spec( array( $this->field( 'text', 'name', array( 'validate' => 'acme:record' ) ) ) );

		$saved = $this->save( $spec, $post_id, array( $this->control_name( 'name' ) => 'O\'Brien C:\\dir' ) );

		self::assertSame( array( array( 'validate', "_{$this->prefix}_name", 'O\'Brien C:\\dir' ) ), $this->records( $saved, 'validate' ) );
	}

	public function test_an_unposted_or_disabled_field_keeps_its_value(): void {
		$post_id = $this->make_post(
			array(
				"_{$this->prefix}_name"   => 'kept',
				"_{$this->prefix}_locked" => 'kept',
				"_{$this->prefix}_flag"   => 'yes',
			)
		);
		$spec    = $this->spec(
			array(
				$this->field( 'text', 'name' ),
				$this->field( 'text', 'locked', array( 'custom_attributes' => array( 'disabled' => 'disabled' ) ) ),
				$this->field( 'checkbox', 'flag', array( 'disabled' => true ) ),
			)
		);

		$this->save(
			$spec,
			$post_id,
			array(),
			array(
				$this->id => array(
					"_{$this->prefix}_locked" => 'tampered',
					"_{$this->prefix}_flag"   => 'no',
				),
			),
			array( array( $this->id, "_{$this->prefix}_name" ) )
		);

		self::assertSame( array( 'kept' ), $this->meta( $post_id, 'name' ) );
		self::assertSame( array( 'kept' ), $this->meta( $post_id, 'locked' ) );
		self::assertSame( array( 'yes' ), $this->meta( $post_id, 'flag' ) );
	}

	public function test_a_posted_key_outside_the_definition_is_ignored(): void {
		$post_id = $this->make_post();
		$spec    = $this->spec( array( $this->field( 'text', 'name' ) ) );

		$this->save( $spec, $post_id, array( $this->control_name( 'name' ) => 'new' ), array( $this->id => array( "_{$this->prefix}_other" => 'injected' ) ) );

		self::assertSame( array( 'new' ), $this->meta( $post_id, 'name' ) );
		self::assertSame( array(), $this->meta( $post_id, 'other' ) );
	}

	#[DataProvider( 'silent_and_worded_rejections' )]
	public function test_a_rejection_keeps_the_value_and_shows_once_as_text_on_the_next_screen( string $behavior, string $message ): void {
		$post_id = $this->make_post( array( "_{$this->prefix}_note" => 'kept' ) );
		$spec    = $this->spec(
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

		$saved = $this->save( $spec, $post_id, array( $this->control_name( 'note' ) => 'too long' ) );
		$next  = $this->follow( $spec, $saved );
		$after = $this->edit_screen( $spec, $post_id );

		self::assertSame( array( 'kept' ), $this->meta( $post_id, 'note' ) );
		self::assertSame( array( $message ), self::rejections( $next['body'] ) );
		self::assertSame( array(), self::rejections( $after['body'] ) );
	}

	public function test_a_membership_rejection_names_the_field_and_what_to_enter(): void {
		$post_id = $this->make_post( array( "_{$this->prefix}_color" => 'red' ) );
		$spec    = $this->spec( array( $this->field( 'select', 'color', array( 'options' => self::COLORS ) ) ) );

		$saved = $this->save( $spec, $post_id, array(), array( $this->id => array( "_{$this->prefix}_color" => 'black' ) ) );

		self::assertSame( array( 'red' ), $this->meta( $post_id, 'color' ) );
		self::assertSame( array( 'Color was not saved, so it keeps its previous value. Choose one of the available options.' ), self::rejections( $this->follow( $spec, $saved )['body'] ) );
	}

	public function test_a_new_post_renders_defaults_and_saves_them_on_publish(): void {
		$spec = $this->spec( array( $this->field( 'text', 'name', array( 'default' => 'fresh' ) ), $this->field( 'checkbox', 'flag' ) ) );

		$screen  = $this->admin_request( 'GET', 'post-new.php', $this->admin, self::FIXTURE, $spec );
		$data    = self::serialize_form(
			$screen['body'],
			'form#post',
			array(
				'post_title'                  => 'Acme post',
				$this->control_name( 'flag' ) => true,
			)
		) + array( 'publish' => 'Publish' );
		$post_id = (int) $data['post_ID'];

		$this->post_ids[] = $post_id;
		$this->admin_request( 'POST', 'post.php', $this->admin, self::FIXTURE, $spec, $data );

		self::assertSame( 'publish', get_post_status( $post_id ) );
		self::assertSame( array( 'fresh' ), $this->meta( $post_id, 'name' ) );
		self::assertSame( array( 'yes' ), $this->meta( $post_id, 'flag' ) );
	}

	public function test_a_nonce_minted_for_another_post_or_none_stores_nothing(): void {
		$post_id  = $this->make_post( array( "_{$this->prefix}_name" => 'kept' ) );
		$other_id = $this->make_post();
		$spec     = $this->spec( array( $this->field( 'text', 'name' ) ) );
		$other    = self::serialize_form( $this->edit_screen( $spec, $other_id )['body'], 'form#post' );

		$this->save( $spec, $post_id, array( $this->control_name( 'name' ) => 'borrowed' ), array( "{$this->id}_nonce" => $other[ "{$this->id}_nonce" ] ) );
		$this->save( $spec, $post_id, array( $this->control_name( 'name' ) => 'unsigned' ), array(), array( "{$this->id}_nonce" ) );

		self::assertSame( array( 'kept' ), $this->meta( $post_id, 'name' ) );
	}

	public function test_a_user_without_the_forms_capability_stores_nothing(): void {
		$post_id = $this->make_post( array( "_{$this->prefix}_name" => 'kept' ) );
		$open    = $this->spec( array( $this->field( 'text', 'name' ) ) );
		$closed  = $this->spec( array( $this->field( 'text', 'name' ) ), array( 'capability' => 'manage_options' ) );

		$data = self::serialize_form( $this->edit_screen( $open, $post_id, $this->editor )['body'], 'form#post', array( $this->control_name( 'name' ) => 'forged' ) ) + array( 'save' => 'Update' );
		$this->admin_request( 'POST', 'post.php', $this->editor, self::FIXTURE, $closed, $data );

		self::assertSame( array( 'kept' ), $this->meta( $post_id, 'name' ) );
	}

	public function test_quick_edit_stores_nothing(): void {
		$post_id = $this->make_post( array( "_{$this->prefix}_name" => 'kept' ) );
		$spec    = $this->spec( array( $this->field( 'text', 'name' ) ) );
		$list    = self::document( $this->admin_request( 'GET', 'edit.php', $this->admin, self::FIXTURE, $spec )['body'] );

		$this->admin_request(
			'POST',
			'admin-ajax.php',
			$this->admin,
			self::FIXTURE,
			$spec,
			array(
				'action'       => 'inline-save',
				'_inline_edit' => (string) $list->querySelector( 'input#_inline_edit' )?->getAttribute( 'value' ),
				'post_ID'      => (string) $post_id,
				'post_type'    => 'post',
				'post_title'   => 'Quickly edited',
				'_status'      => 'publish',
				'screen'       => 'edit-post',
				'post_view'    => 'list',
				$this->id      => array( "_{$this->prefix}_name" => 'quick' ),
			)
		);

		self::assertSame( 'Quickly edited', get_the_title( $post_id ) );
		self::assertSame( array( 'kept' ), $this->meta( $post_id, 'name' ) );
	}

	public function test_bulk_edit_stores_nothing(): void {
		$post_id = $this->make_post( array( "_{$this->prefix}_name" => 'kept' ) );
		$spec    = $this->spec( array( $this->field( 'text', 'name' ) ) );
		$list    = self::document( $this->admin_request( 'GET', 'edit.php', $this->admin, self::FIXTURE, $spec )['body'] );

		$bulk = $this->admin_request(
			'POST',
			'edit.php',
			$this->admin,
			self::FIXTURE,
			$spec,
			array(
				'action'    => 'edit',
				'bulk_edit' => 'Update',
				'post_type' => 'post',
				'_wpnonce'  => (string) $list->querySelector( 'form#posts-filter input#_wpnonce' )?->getAttribute( 'value' ),
				'post'      => array( (string) $post_id ),
				'_status'   => 'private',
				$this->id   => array( "_{$this->prefix}_name" => 'bulk' ),
			)
		);

		self::assertIsString( $bulk['location'] );
		self::assertSame( 'private', get_post_status( $post_id ) );
		self::assertSame( array( 'kept' ), $this->meta( $post_id, 'name' ) );
	}

	public function test_a_save_without_the_forms_nonce_stores_nothing(): void {
		$post_id = $this->make_post( array( "_{$this->prefix}_name" => 'kept' ) );
		$form    = new ObjectForm(
			$this->id,
			new RecordedDefinition(
				fn (): array => array(
					'title'      => 'Acme details',
					'capability' => 'edit_posts',
					'fields'     => array( $this->field( 'text', 'name' ) ),
				)
			),
			new PostMetaBox( array( 'post' ) ),
			new AdminNotices( $this->prefix, new ErrorLogLogger( 'acme' ) )
		);
		$form->register_hooks();
		wp_set_current_user( $this->admin );
		$_POST[ $this->id ] = array( "_{$this->prefix}_name" => 'programmatic' );

		try {
			wp_update_post(
				array(
					'ID'         => $post_id,
					'post_title' => 'Updated in code',
				)
			);
		} finally {
			unset( $_POST[ $this->id ] );
			wp_set_current_user( 0 );
		}

		self::assertSame( 'Updated in code', get_the_title( $post_id ) );
		self::assertSame( array( 'kept' ), $this->meta( $post_id, 'name' ) );
	}

	public function test_previewing_a_published_post_stores_nothing(): void {
		$post_id = $this->make_post( array( "_{$this->prefix}_name" => 'kept' ) );
		$spec    = $this->spec( array( $this->field( 'text', 'name' ) ) );

		$changes = array(
			'content'                     => 'Previewed content',
			$this->control_name( 'name' ) => 'previewed',
		);
		$data    = \array_replace( self::serialize_form( $this->edit_screen( $spec, $post_id )['body'], 'form#post', $changes ), array( 'wp-preview' => 'dopreview' ) );
		$this->admin_request( 'POST', 'post.php', $this->admin, self::FIXTURE, $spec, $data );

		self::assertSame( array( 'kept' ), $this->meta( $post_id, 'name' ) );
		self::assertNotFalse( wp_get_post_autosave( $post_id, $this->admin ) );
	}

	public function test_a_drafts_heartbeat_autosave_stores_nothing(): void {
		$post_id = $this->make_post( array( "_{$this->prefix}_name" => 'kept' ), 'post', 'draft' );
		$spec    = $this->spec( array( $this->field( 'text', 'name' ) ) );
		$screen  = $this->edit_screen( $spec, $post_id );
		$form    = self::serialize_form( $screen['body'], 'form#post' );

		$this->admin_request(
			'POST',
			'admin-ajax.php',
			$this->admin,
			self::FIXTURE,
			$spec,
			array(
				'action'    => 'heartbeat',
				'_nonce'    => self::heartbeat_nonce( $screen['body'] ),
				'screen_id' => 'post',
				'has_focus' => 'true',
				'data'      => array(
					'wp_autosave' => array(
						'post_id'     => (string) $post_id,
						'post_type'   => 'post',
						'post_author' => (string) $this->admin,
						'post_title'  => 'Autosaved',
						'content'     => '',
						'excerpt'     => '',
						'_wpnonce'    => $form['_wpnonce'],
					),
				),
			)
		);

		self::assertSame( 'Autosaved', get_the_title( $post_id ) );
		self::assertSame( array( 'kept' ), $this->meta( $post_id, 'name' ) );
	}

	public function test_a_nested_update_of_the_same_post_saves_and_reports_once(): void {
		$post_id = $this->make_post();
		$spec    = $this->spec(
			array(
				$this->field( 'text', 'name', array( 'validate' => 'acme:record' ) ),
				$this->field( 'text', 'note', array( 'validate' => 'acme:reject' ) ),
			),
			array(),
			array( 'nested_update' => true )
		);

		$saved = $this->save(
			$spec,
			$post_id,
			array(
				$this->control_name( 'name' ) => 'once',
				$this->control_name( 'note' ) => 'too long',
			)
		);

		self::assertCount( 2, $this->records( $saved, 'validate' ) );
		self::assertSame( array( 'once' ), $this->meta( $post_id, 'name' ) );
		self::assertCount( 1, self::rejections( $this->follow( $spec, $saved )['body'] ) );
	}

	public function test_a_custom_store_receives_the_reads_and_the_writes(): void {
		$post_id = $this->make_post();
		$spec    = $this->spec( array( $this->field( 'text', 'name' ) ), array(), array( 'store' => 'acme:recording' ) );

		$screen = $this->edit_screen( $spec, $post_id );
		$saved  = $this->save( $spec, $post_id, array( $this->control_name( 'name' ) => 'stored' ) );
		$again  = $this->edit_screen( $spec, $post_id );

		self::assertContains( array( 'get_values', $post_id, array( "_{$this->prefix}_name" ) ), $screen['records'] );
		self::assertContains( array( 'update_values', $post_id, array( "_{$this->prefix}_name" => 'stored' ) ), $saved['records'] );
		self::assertSame( 'stored', $this->control( $this->box( self::document( $again['body'] ) ), 'name' )->getAttribute( 'value' ) );
		self::assertSame( array(), $this->meta( $post_id, 'name' ) );
	}

	public function test_the_block_editors_meta_box_save_stores_as_the_classic_save_does(): void {
		$post_id = $this->make_post( array( "_{$this->prefix}_flag" => 'yes' ) );
		$spec    = $this->spec( array( $this->field( 'text', 'name' ), $this->field( 'checkbox', 'flag' ) ), array(), array( 'editor' => 'block' ) );

		$this->block_editor_save(
			$spec,
			$post_id,
			array(
				$this->control_name( 'name' ) => 'O\'Brien C:\\dir',
				$this->control_name( 'flag' ) => false,
			)
		);

		self::assertSame( array( 'O\'Brien C:\\dir' ), $this->meta( $post_id, 'name' ) );
		self::assertSame( array( 'no' ), $this->meta( $post_id, 'flag' ) );
	}

	// The block editor's meta box save follows the redirect to the edit screen and discards that page, so the editor's script fetches the rejection afterwards.
	public function test_a_rejection_in_the_block_editor_reaches_the_editor_after_the_save(): void {
		$post_id = $this->make_post( array( "_{$this->prefix}_note" => 'kept' ) );
		$spec    = $this->spec( array( $this->field( 'text', 'note', array( 'validate' => 'acme:reject' ) ) ), array(), array( 'editor' => 'block' ) );

		$saved      = $this->block_editor_save( $spec, $post_id, array( $this->control_name( 'note' ) => 'too long' ) );
		$redirected = $this->follow( $spec, $saved );
		wp_set_current_user( $this->admin );
		$delivered = ( new AdminNotices( $this->id, new ErrorLogLogger( 'acme' ) ) )->rest_deliver();
		$list      = $this->admin_request( 'GET', 'edit.php', $this->admin, self::FIXTURE, $spec );

		self::assertSame( array( 'kept' ), $this->meta( $post_id, 'note' ) );
		self::assertNotNull( self::document( $redirected['body'] )->querySelector( 'form.metabox-base-form' ) );
		self::assertSame( array( 'Note was not saved, so it keeps its previous value. Use at most four characters.' ), \array_column( $delivered, 'content' ) );
		self::assertSame( array(), self::rejections( $list['body'] ) );
	}

	#[DataProvider( 'heartbeat_refreshes' )]
	public function test_the_heartbeat_refreshes_the_forms_nonce_for_its_post( string $refresh ): void {
		$post_id = $this->make_post();
		$spec    = $this->spec( array( $this->field( 'text', 'name' ) ) );
		$form    = self::serialize_form( $this->edit_screen( $spec, $post_id )['body'], 'form#post' );

		$reply = $this->heartbeat( $spec, $this->admin, array( $refresh => array( 'post_id' => (string) $post_id ) ) );

		self::assertSame( $form[ "{$this->id}_nonce" ], $reply[ $refresh ]['replace'][ "{$this->id}_nonce" ] ?? null );
		self::assertArrayHasKey( '_wpnonce', $reply[ $refresh ]['replace'] );
	}

	public function test_the_heartbeat_refreshes_no_form_nonce_for_a_user_who_may_not_edit_with_the_form_or_another_post_type(): void {
		$post_id = $this->make_post();
		$page_id = $this->make_post( array(), 'page' );
		$spec    = $this->spec( array( $this->field( 'text', 'name' ) ), array( 'capability' => 'manage_options' ) );

		$editor = $this->heartbeat( $spec, $this->editor, array( 'wp-refresh-post-nonces' => array( 'post_id' => (string) $post_id ) ) );
		$page   = $this->heartbeat( $spec, $this->admin, array( 'wp-refresh-post-nonces' => array( 'post_id' => (string) $page_id ) ) );

		self::assertArrayHasKey( '_wpnonce', $editor['wp-refresh-post-nonces']['replace'] );
		self::assertArrayNotHasKey( "{$this->id}_nonce", $editor['wp-refresh-post-nonces']['replace'] );
		self::assertArrayHasKey( '_wpnonce', $page['wp-refresh-post-nonces']['replace'] );
		self::assertArrayNotHasKey( "{$this->id}_nonce", $page['wp-refresh-post-nonces']['replace'] );
	}

	public function test_only_the_block_editor_prints_the_script_that_refreshes_the_forms_nonce(): void {
		$post_id = $this->make_post();
		$fields  = array( $this->field( 'text', 'name' ) );

		$block   = $this->edit_screen( $this->spec( $fields, array(), array( 'editor' => 'block' ) ), $post_id )['body'];
		$classic = $this->edit_screen( $this->spec( $fields ), $post_id )['body'];

		$scripts = self::scripts_naming( $block, "'{$this->id}_nonce'" );
		self::assertCount( 1, $scripts );
		self::assertStringContainsString( "'heartbeat-tick'", $scripts[0] );
		self::assertStringContainsString( "['wp-refresh-metabox-loader-nonces']", $scripts[0] );
		self::assertSame( array(), self::scripts_naming( $classic, "'{$this->id}_nonce'" ) );
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

	protected function control_name( string $name ): string {
		return "{$this->id}[_{$this->prefix}_$name]";
	}

	// The form posts as the Update button submits it, after the user set the named controls; tampering replaces posted keys and removes posted keys or a key of the form's array.
	protected function save( array $spec, int $post_id, array $changes = array(), array $tampered = array(), array $removed = array() ): array {
		$data = \array_replace_recursive( self::serialize_form( $this->edit_screen( $spec, $post_id )['body'], 'form#post', $changes ), $tampered ) + array( 'save' => 'Update' );
		foreach ( $removed as $name ) {
			if ( \is_array( $name ) ) {
				self::assertArrayHasKey( $name[1], $data[ $name[0] ] );
				unset( $data[ $name[0] ][ $name[1] ] );
			} else {
				unset( $data[ $name ] );
			}
		}

		return $this->admin_request( 'POST', 'post.php', $this->admin, self::FIXTURE, $spec, $data );
	}

	protected function follow( array $spec, array $saved ): array {
		self::assertIsString( $saved['location'] );

		return $this->admin_request( 'GET', \explode( '/wp-admin/', $saved['location'], 2 )[1], $this->admin, self::FIXTURE, $spec );
	}

	protected function meta( int $post_id, string $name ): array {
		return get_post_meta( $post_id, "_{$this->prefix}_$name", false );
	}

	protected function records( array $request, string $kind ): array {
		return \array_values( \array_filter( $request['records'], static fn ( array $record ): bool => $kind === $record[0] ) );
	}

	protected static function rejections( string $html ): array {
		$messages = array();
		foreach ( self::document( $html )->querySelectorAll( 'div.notice-error.is-dismissible > p' ) as $message ) {
			$messages[] = \trim( $message->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		}

		return $messages;
	}

	// The block editor posts its meta box forms with the base form to the meta box loader's URL, as its requestMetaBoxUpdates() does.
	protected function block_editor_save( array $spec, int $post_id, array $changes = array() ): array {
		$body = $this->edit_screen( $spec, $post_id )['body'];
		$data = \array_replace_recursive( self::serialize_form( $body, 'form.metabox-base-form' ), self::serialize_form( $body, 'form.metabox-location-advanced', $changes ) );
		self::assertSame( 1, \preg_match( '/var _wpMetaBoxUrl = ("[^"]+");/', $body, $matches ) );

		return $this->admin_request( 'POST', \explode( '/wp-admin/', (string) \json_decode( $matches[1] ), 2 )[1], $this->admin, self::FIXTURE, $spec, $data );
	}

	// A heartbeat nonce that is no longer fresh makes WordPress refresh the screen's nonces, as it does for a page left open.
	protected function heartbeat( array $spec, int $user_id, array $data ): array {
		$reply = $this->admin_request(
			'POST',
			'admin-ajax.php',
			$user_id,
			self::FIXTURE,
			$spec,
			array(
				'action'    => 'heartbeat',
				'_nonce'    => 'expired',
				'screen_id' => 'post',
				'data'      => $data,
			)
		);
		$json  = \json_decode( $reply['body'], true );
		self::assertIsArray( $json, $reply['body'] );

		return $json;
	}

	protected static function scripts_naming( string $html, string $needle ): array {
		$scripts = array();
		foreach ( self::document( $html )->querySelectorAll( 'script' ) as $script ) {
			if ( \str_contains( $script->textContent, $needle ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
				$scripts[] = $script->textContent; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
			}
		}

		return $scripts;
	}

	protected static function heartbeat_nonce( string $html ): string {
		self::assertSame( 1, \preg_match( '/var heartbeatSettings = \{"nonce":"([0-9a-f]+)"/', $html, $matches ) );

		return $matches[1];
	}

	protected function make_post( array $meta = array(), string $type = 'post', string $status = 'publish' ): int {
		$post_id = wp_insert_post(
			array(
				'post_type'   => $type,
				'post_title'  => 'Acme post',
				'post_status' => $status,
				'post_author' => $this->admin,
			)
		);
		self::assertIsInt( $post_id );
		foreach ( $meta as $key => $value ) {
			add_post_meta( $post_id, $key, wp_slash( $value ) );
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
			wp_delete_post( $post_id, true );
		}
	}
}
