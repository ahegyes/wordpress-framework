<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Settings\AdminPage;

use DeepWebSolutions\Framework\Settings\AdminPage\OptionsPage;
use DeepWebSolutions\Framework\Shared\Exception\InvalidArgumentException;
use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesStorageTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\ProvidesHelperIdsTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\RecordedDefinition;
use DeepWebSolutions\Framework\Tests\Integration\Support\RecordsDoingItWrongTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\RunsAdminRequestsTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\SerializesFormsTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( OptionsPage::class )]
final class OptionsPageTest extends TestCase {
	use CreatesUsersTrait;
	use IsolatesHooksTrait;
	use IsolatesStorageTrait;
	use ProvidesHelperIdsTrait;
	use RecordsDoingItWrongTrait;
	use RunsAdminRequestsTrait;
	use SerializesFormsTrait;

	protected const string FIXTURE = __DIR__ . '/../../../Fixtures/Settings/acme-options-page.php';

	protected const array COLORS = array(
		'red'  => 'Red',
		'blue' => 'Blue',
		'teal' => 'Teal',
	);

	protected const array NUMBERED = array(
		1 => 'One',
		2 => 'Two',
		3 => 'Three',
	);

	protected int $admin;
	protected int $editor;
	protected string $id;
	protected string $group;
	protected mixed $pagenow;

	#[\Override]
	protected function setUp(): void {
		$this->admin   = $this->make_user( 'acme_options_admin', 'administrator' );
		$this->editor  = $this->make_user( 'acme_options_editor', 'editor' );
		$this->id      = "{$this->prefix}_settings";
		$this->group   = "{$this->id}_general";
		$this->pagenow = $GLOBALS['pagenow'] ?? null;
	}

	#[\Override]
	protected function tearDown(): void {
		delete_transient( 'settings_errors' );
		$GLOBALS['pagenow'] = $this->pagenow; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restores what an in-process row replaced.
		unset( $_REQUEST['option_page'] );
		foreach ( \array_keys( get_registered_settings() ) as $name ) {
			if ( \str_starts_with( $name, $this->prefix ) ) {
				unregister_setting( $this->group, $name );
			}
		}
	}

	public static function parents(): array {
		return array(
			'settings'      => array( 'options-general.php' ),
			'custom-parent' => array( 'tools.php' ),
		);
	}

	public static function multi_value_types(): array {
		return array(
			'multiselect' => array( 'multiselect' ),
			'checkboxes'  => array( 'checkboxes' ),
		);
	}

	public static function relocated_option_pages(): array {
		return array(
			'in-the-query-string' => array( 'query-string' ),
			'padded'              => array( 'padded' ),
		);
	}

	#[DataProvider( 'invalid_helper_ids' )]
	public function test_an_invalid_id_is_rejected( string $id ): void {
		$this->expectException( InvalidArgumentException::class );

		new OptionsPage( $this->identity, $id, new RecordedDefinition( static fn (): array => array() ) );
	}

	#[DataProvider( 'v1_helper_ids' )]
	public function test_every_v1_key_is_a_valid_id( string $id ): void {
		$this->expectNotToPerformAssertions();

		new OptionsPage( $this->identity, $id, new RecordedDefinition( static fn (): array => array() ) );
	}

	public function test_the_definition_is_evaluated_neither_at_construction_nor_at_registration(): void {
		$definition = new RecordedDefinition( static fn (): array => array() );
		$page       = new OptionsPage( $this->identity, $this->id, $definition );

		$page->register_hooks();

		self::assertSame( array(), $definition->calls );
	}

	public function test_the_definition_receives_the_pages_id_once(): void {
		$definition = new RecordedDefinition(
			static fn (): array => array(
				'parent'     => 'options-general.php',
				'capability' => 'manage_options',
				'page_title' => 'Acme',
				'menu_title' => 'Acme',
				'tabs'       => array(),
			)
		);
		$page       = new OptionsPage( $this->identity, $this->id, $definition );

		$page->get_capability();
		$page->get_capability();

		self::assertSame( array( array( $this->id ) ), $definition->calls );
	}

	public function test_only_the_tab_a_request_shows_or_saves_asks_for_its_fields(): void {
		$spec = $this->spec(
			array( $this->field( 'text', 'name' ) ),
			array( 'tabs' => array( self::tab( 'general' ), self::tab( 'second' ) ) ),
			array( 'fields' => array( 'second' => array( $this->field( 'text', 'other' ) ) ) )
		);

		$elsewhere = $this->admin_request( 'GET', 'index.php', $this->admin, self::FIXTURE, $spec );
		$shown     = $this->open( $spec, $this->admin, 'second' );
		$saved     = $this->save( $spec, $this->admin, self::form_data( $shown['body'], array( "{$this->prefix}_other" => 'new' ) ) );

		self::assertArrayNotHasKey( 'asked', $elsewhere );
		self::assertSame( array( 'second' ), $shown['asked'] );
		self::assertSame( array( 'second' ), $saved['asked'] );
		self::assertSame( 'new', get_option( "{$this->prefix}_other" ) );
	}

	public function test_a_field_id_outside_the_plugins_prefix_or_the_helper_id_pattern_is_dropped_with_a_notice(): void {
		$this->record_doing_it_wrong();
		$fields                  = array(
			array(
				'type' => 'text',
				'id'   => "{$this->prefix}_name",
			),
			array(
				'type' => 'text',
				'id'   => "{$this->prefix}x_name",
			),
			array(
				'type' => 'text',
				'id'   => "{$this->prefix}_Name",
			),
		);
		$page                    = new OptionsPage(
			$this->identity,
			$this->id,
			new RecordedDefinition( fn ( string $page_id, ?string $tab_slug = null ): array => \is_null( $tab_slug ) ? $this->spec( array() )['page'] : $fields )
		);
		$GLOBALS['pagenow']      = 'options.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Runs the registration as options.php does.
		$_REQUEST['option_page'] = $this->group;

		$page->register_settings();

		self::assertSame( array( "{$this->prefix}_name" ), \array_values( \array_filter( \array_keys( get_registered_settings() ), fn ( string $name ): bool => \str_starts_with( $name, $this->prefix ) ) ) );
		self::assertSame( array( OptionsPage::class . '::get_fields', OptionsPage::class . '::get_fields' ), $this->doing_it_wrong );
	}

	public function test_a_dropped_field_neither_renders_nor_stores_what_is_posted_for_it(): void {
		$spec = $this->spec( array( $this->field( 'text', 'name' ) ), array(), array( 'contributed' => array( 'fields' => array( 'general' => array( $this->field( 'text', 'blogname', array( 'id' => "{$this->prefix}x_blogname" ) ) ) ) ) ) );
		$form = $this->open( $spec, $this->admin );
		$data = self::form_data( $form['body'], array( "{$this->prefix}_name" => 'new' ) ) + array( "{$this->prefix}x_blogname" => 'injected' );

		$this->save( $spec, $this->admin, $data );

		self::assertSame( array( 'Name' ), self::row_titles( $form['body'] ) );
		self::assertCount( 1, $form['doing_it_wrong'] ?? array() );
		self::assertStringContainsString( "{$this->prefix}x_blogname", $form['doing_it_wrong'][0][1] ?? '' );
		self::assertSame( 'new', get_option( "{$this->prefix}_name" ) );
		self::assertFalse( get_option( "{$this->prefix}x_blogname" ) );
	}

	public function test_each_field_registers_in_the_tabs_group_typed_by_the_value_it_stores(): void {
		$fields                  = array(
			$this->field( 'text', 'name' ),
			$this->field( 'checkbox', 'flag' ),
			$this->field( 'multiselect', 'colors', array( 'options' => self::COLORS ) ),
			$this->field( 'checkboxes', 'days', array( 'options' => self::NUMBERED ) ),
		);
		$page                    = new OptionsPage(
			$this->identity,
			$this->id,
			new RecordedDefinition( fn ( string $page_id, ?string $tab_slug = null ): array => \is_null( $tab_slug ) ? $this->spec( array(), array( 'capability' => 'edit_pages' ) )['page'] : $fields )
		);
		$GLOBALS['pagenow']      = 'options.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Runs the registration as options.php does.
		$_REQUEST['option_page'] = $this->group;

		$page->register_settings();

		$registered = get_registered_settings();
		self::assertSame(
			array(
				"{$this->prefix}_name"   => array( $this->group, 'string', false ),
				"{$this->prefix}_flag"   => array( $this->group, 'string', false ),
				"{$this->prefix}_colors" => array( $this->group, 'array', false ),
				"{$this->prefix}_days"   => array( $this->group, 'array', false ),
			),
			\array_map( static fn ( array $setting ): array => array( $setting['group'], $setting['type'], $setting['show_in_rest'] ), \array_intersect_key( $registered, \array_flip( \array_column( $fields, 'id' ) ) ) )
		);
		self::assertSame( 'edit_pages', apply_filters( "option_page_capability_$this->group", 'manage_options' ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Applies core's filter.
	}

	public function test_a_request_outside_options_php_registers_no_setting(): void {
		$page                    = new OptionsPage( $this->identity, $this->id, new RecordedDefinition( fn ( string $page_id, ?string $tab_slug = null ): array => \is_null( $tab_slug ) ? $this->spec( array() )['page'] : array( $this->field( 'text', 'name' ) ) ) );
		$GLOBALS['pagenow']      = 'admin-post.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Runs the registration as another admin screen does.
		$_REQUEST['option_page'] = $this->group;

		$page->register_settings();

		self::assertArrayNotHasKey( "{$this->prefix}_name", get_registered_settings() );
	}

	public function test_a_tab_without_a_field_to_save_renders_no_form(): void {
		$intro = array(
			'type'  => 'title',
			'title' => 'Intro',
		);
		$form  = $this->open( $this->spec( array( $intro ) ), $this->admin );
		$page  = self::document( $form['body'] );

		self::assertSame( 'Acme settings', $page->querySelector( '.wrap h1' )?->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		self::assertNull( $page->querySelector( 'form[action="options.php"]' ) );
		self::assertSame( array( 'general' ), $form['asked'] );
		self::assertNotContains( OptionsPage::class . '::get_fields', \array_column( $form['doing_it_wrong'] ?? array(), 0 ) );
	}

	public function test_a_first_save_validates_once_and_stores_the_result_without_an_error(): void {
		$spec = $this->spec( array( $this->field( 'text', 'name', array( 'validate' => 'acme:append' ) ) ) );
		$data = self::form_data( $this->open( $spec, $this->admin )['body'], array( "{$this->prefix}_name" => 'v' ) );

		$saved = $this->save( $spec, $this->admin, $data );

		self::assertSame( 'v!', get_option( "{$this->prefix}_name" ) );
		self::assertSame( array( array( 'validate', "{$this->prefix}_name", 'v' ) ), $saved['records'] );
		self::assertSame( array( 'Settings saved.' ), self::notices( $this->follow( $spec, $this->admin, $saved )['body'] ) );
	}

	public function test_a_programmatic_update_outside_options_php_is_stored_as_given(): void {
		$spec = $this->spec( array( $this->field( 'text', 'name', array( 'validate' => 'acme:append' ) ) ), array(), array( 'update' => array( "{$this->prefix}_name" => ' <b>raw</b> ' ) ) );

		$request = $this->admin_request( 'GET', "admin-post.php?option_page=$this->group", $this->admin, self::FIXTURE, $spec );

		self::assertSame( ' <b>raw</b> ', get_option( "{$this->prefix}_name" ) );
		self::assertSame( array(), $request['records'] );
	}

	#[DataProvider( 'parents' )]
	public function test_a_rejected_field_keeps_its_value_and_its_message_shows_once( string $parent_file ): void {
		update_option( "{$this->prefix}_name", 'old' );
		$spec = $this->spec( array( $this->field( 'text', 'name', array( 'validate' => 'acme:reject' ) ) ), array( 'parent' => $parent_file ) );
		$data = self::form_data( $this->open( $spec, $this->admin )['body'], array( "{$this->prefix}_name" => 'too long' ) );

		$saved = $this->save( $spec, $this->admin, $data );

		self::assertSame( 'old', get_option( "{$this->prefix}_name" ) );
		self::assertSame( array( 'Name was not saved and keeps its previous value. The other changes were saved. Use at most four characters.' ), self::notices( $this->follow( $spec, $this->admin, $saved )['body'] ) );
	}

	public function test_a_rejected_field_never_stored_stays_absent(): void {
		$spec = $this->spec( array( $this->field( 'text', 'name', array( 'validate' => 'acme:reject' ) ) ) );
		$data = self::form_data( $this->open( $spec, $this->admin )['body'], array( "{$this->prefix}_name" => 'too long' ) );

		$this->save( $spec, $this->admin, $data );

		self::assertFalse( get_option( "{$this->prefix}_name" ) );
	}

	#[DataProvider( 'parents' )]
	public function test_a_valid_save_shows_settings_saved_once( string $parent_file ): void {
		$spec = $this->spec( array( $this->field( 'text', 'name' ) ), array( 'parent' => $parent_file ) );
		$data = self::form_data( $this->open( $spec, $this->admin )['body'], array( "{$this->prefix}_name" => 'new' ) );

		$saved = $this->save( $spec, $this->admin, $data );

		self::assertSame( 'new', get_option( "{$this->prefix}_name" ) );
		self::assertSame( array( 'Settings saved.' ), self::notices( $this->follow( $spec, $this->admin, $saved )['body'] ) );
	}

	public function test_a_rejection_without_a_message_says_that_the_value_is_not_valid(): void {
		update_option( "{$this->prefix}_name", 'old' );
		$spec = $this->spec( array( $this->field( 'text', 'name', array( 'validate' => 'acme:reject-silently' ) ) ) );
		$data = self::form_data( $this->open( $spec, $this->admin )['body'], array( "{$this->prefix}_name" => 'new' ) );

		$saved = $this->save( $spec, $this->admin, $data );

		self::assertSame( 'old', get_option( "{$this->prefix}_name" ) );
		self::assertSame( array( 'Name was not saved because its value is not valid, and keeps its previous value. The other changes were saved.' ), self::notices( $this->follow( $spec, $this->admin, $saved )['body'] ) );
	}

	public function test_a_built_in_rejection_carries_what_to_enter(): void {
		update_option( "{$this->prefix}_email", 'ops@example.com' );
		$spec = $this->spec( array( $this->field( 'email', 'email' ) ) );
		$data = self::form_data( $this->open( $spec, $this->admin )['body'], array( "{$this->prefix}_email" => 'not an email' ) );

		$saved = $this->save( $spec, $this->admin, $data );

		self::assertSame( 'ops@example.com', get_option( "{$this->prefix}_email" ) );
		self::assertSame( array( 'Email was not saved and keeps its previous value. The other changes were saved. Enter a valid email address, such as name@example.com.' ), self::notices( $this->follow( $spec, $this->admin, $saved )['body'] ) );
	}

	public function test_titles_and_messages_print_as_text_and_an_untitled_field_is_labelled_and_named_by_its_id(): void {
		$site = "{$this->prefix}_site";
		$spec = $this->spec(
			array(
				$this->field( 'email', 'email', array( 'title' => '<em>Email</em>' ) ),
				array(
					'type' => 'url',
					'id'   => $site,
				),
			)
		);
		$form = $this->open( $spec, $this->admin )['body'];
		$data = self::form_data(
			$form,
			array(
				"{$this->prefix}_email" => 'not an email',
				$site                   => 'javascript:alert(1)',
			)
		);

		$page = $this->follow( $spec, $this->admin, $this->save( $spec, $this->admin, $data ) )['body'];

		self::assertSame( array( '<em>Email</em>', $site ), self::row_titles( $form ) );
		self::assertSame( $site, self::document( $form )->querySelectorAll( 'form tr' )->item( 1 )?->querySelector( 'th label' )?->getAttribute( 'for' ) );
		self::assertNull( self::document( $form )->querySelector( 'form em' ) );
		self::assertSame(
			array(
				'<em>Email</em> was not saved and keeps its previous value. The other changes were saved. Enter a valid email address, such as name@example.com.',
				"$site was not saved and keeps its previous value. The other changes were saved. Enter a valid URL, such as https://example.com.",
			),
			self::notices( $page )
		);
		self::assertNull( self::document( $page )->querySelector( '.settings-error em' ) );
	}

	#[DataProvider( 'multi_value_types' )]
	public function test_a_fully_cleared_multi_value_field_stores_an_empty_array( string $type ): void {
		update_option( "{$this->prefix}_colors", array( 'red', 'blue' ) );
		$spec = $this->spec( array( $this->field( $type, 'colors', array( 'options' => self::COLORS ) ) ) );
		$data = self::form_data( $this->open( $spec, $this->admin )['body'], array( "{$this->prefix}_colors" => array() ) );

		$this->save( $spec, $this->admin, $data );

		self::assertSame( array(), get_option( "{$this->prefix}_colors" ) );
	}

	public function test_an_unchecked_checkbox_stores_no_while_a_text_field_posts(): void {
		update_option( "{$this->prefix}_alpha", 'yes' );
		update_option( "{$this->prefix}_beta", 'yes' );
		update_option( "{$this->prefix}_name", 'old' );
		$spec = $this->spec( array( $this->field( 'checkbox', 'alpha' ), $this->field( 'checkbox', 'beta' ), $this->field( 'text', 'name' ) ) );
		$data = self::form_data(
			$this->open( $spec, $this->admin )['body'],
			array(
				"{$this->prefix}_beta" => false,
				"{$this->prefix}_name" => 'new',
			)
		);

		$this->save( $spec, $this->admin, $data );

		self::assertSame( array( 'yes', 'no', 'new' ), $this->stored( 'alpha', 'beta', 'name' ) );
	}

	public function test_a_disabled_field_keeps_its_value_and_renders_no_hidden_input(): void {
		update_option( "{$this->prefix}_name", 'old' );
		update_option( "{$this->prefix}_agree", 'yes' );
		update_option( "{$this->prefix}_colors", array( 'red' ) );
		update_option( "{$this->prefix}_other", 'old' );
		$spec = $this->spec(
			array(
				$this->field( 'text', 'name', array( 'custom_attributes' => array( 'disabled' => 'disabled' ) ) ),
				$this->field( 'checkbox', 'agree', array( 'disabled' => true ) ),
				$this->field(
					'multiselect',
					'colors',
					array(
						'options'           => self::COLORS,
						'custom_attributes' => array( 'disabled' => 'disabled' ),
					)
				),
				$this->field( 'text', 'other' ),
			)
		);
		$form = $this->open( $spec, $this->admin )['body'];

		$this->save( $spec, $this->admin, self::form_data( $form, array( "{$this->prefix}_other" => 'new' ) ) );

		self::assertSame( array( 'old', 'yes', array( 'red' ), 'new' ), $this->stored( 'name', 'agree', 'colors', 'other' ) );
		self::assertSame( array( "{$this->prefix}_other" ), \array_values( \array_filter( \array_keys( self::form_data( $form ) ), fn ( string $name ): bool => \str_starts_with( $name, $this->prefix ) ) ) );
		self::assertCount( 0, self::document( $form )->querySelectorAll( 'form input[type="hidden"][name^="' . $this->prefix . '"]' ) );
	}

	public function test_a_tab_whose_every_control_is_disabled_leaves_its_rows_unchanged(): void {
		update_option( "{$this->prefix}_name", 'old' );
		$spec = $this->spec(
			array(
				$this->field(
					'text',
					'name',
					array(
						'validate'          => 'acme:record',
						'custom_attributes' => array( 'disabled' => 'disabled' ),
					)
				),
			)
		);
		$data = self::form_data( $this->open( $spec, $this->admin )['body'] );

		$saved = $this->save( $spec, $this->admin, $data );

		self::assertArrayNotHasKey( "{$this->prefix}_name", $data );
		self::assertSame( 'old', get_option( "{$this->prefix}_name" ) );
		self::assertSame( array(), $saved['records'] );
	}

	public function test_a_capability_above_manage_options_refuses_an_administrators_save(): void {
		$spec = $this->spec( array( $this->field( 'text', 'name' ) ), array( 'capability' => 'acme_manage_settings' ) );
		$data = array(
			'option_page'          => $this->group,
			'action'               => 'update',
			"{$this->prefix}_name" => 'new',
		);

		$saved = $this->save( $spec, $this->admin, $data );

		self::assertSame( 403, $saved['died']['status'] ?? null );
		self::assertStringContainsString( 'Sorry, you are not allowed to manage options for this site.', $saved['died']['message'] ?? '' );
		self::assertFalse( get_option( "{$this->prefix}_name" ) );
	}

	public function test_a_capability_below_manage_options_lets_its_holder_save(): void {
		$spec = $this->spec( array( $this->field( 'text', 'name' ) ), array( 'capability' => 'edit_pages' ) );
		$data = self::form_data( $this->open( $spec, $this->editor )['body'], array( "{$this->prefix}_name" => 'new' ) );

		$this->save( $spec, $this->editor, $data );

		self::assertSame( 'new', get_option( "{$this->prefix}_name" ) );
	}

	#[DataProvider( 'relocated_option_pages' )]
	public function test_an_option_page_outside_the_post_body_or_padded_still_validates( string $variant ): void {
		update_option( "{$this->prefix}_size", 's' );
		$spec                           = $this->spec(
			array(
				$this->field(
					'select',
					'size',
					array(
						'options' => array(
							's' => 'Small',
							'm' => 'Medium',
						),
					)
				),
			)
		);
		$data                           = self::form_data( $this->open( $spec, $this->admin )['body'] );
		$data[ "{$this->prefix}_size" ] = 'xl';
		$query                          = '';
		if ( 'query-string' === $variant ) {
			unset( $data['option_page'] );
			$query = '?option_page=' . \rawurlencode( $this->group );
		} else {
			$data['option_page'] = " $this->group ";
		}

		$saved = $this->save( $spec, $this->admin, $data, $query );

		self::assertNull( $saved['died'] );
		self::assertSame( 's', get_option( "{$this->prefix}_size" ) );
	}

	#[DataProvider( 'multi_value_types' )]
	public function test_two_selections_store_exactly_those_two( string $type ): void {
		$spec = $this->spec( array( $this->field( $type, 'colors', array( 'options' => self::COLORS ) ) ) );
		$data = self::form_data( $this->open( $spec, $this->admin )['body'], array( "{$this->prefix}_colors" => array( 'red', 'teal' ) ) );

		$this->save( $spec, $this->admin, $data );

		self::assertSame( array( 'red', 'teal' ), get_option( "{$this->prefix}_colors" ) );
	}

	#[DataProvider( 'multi_value_types' )]
	public function test_a_cleared_multi_value_field_reaches_validate_as_an_empty_array( string $type ): void {
		update_option( "{$this->prefix}_colors", array( 'red' ) );
		$spec = $this->spec(
			array(
				$this->field(
					$type,
					'colors',
					array(
						'options'  => self::COLORS,
						'validate' => 'acme:record',
					)
				),
			)
		);
		$data = self::form_data( $this->open( $spec, $this->admin )['body'], array( "{$this->prefix}_colors" => array() ) );

		$saved = $this->save( $spec, $this->admin, $data );

		self::assertSame( array( array( 'validate', "{$this->prefix}_colors", array() ) ), $saved['records'] );
		self::assertSame( array(), get_option( "{$this->prefix}_colors" ) );
	}

	public function test_a_tampered_value_for_a_disabled_field_is_ignored(): void {
		update_option( "{$this->prefix}_name", 'old' );
		update_option( "{$this->prefix}_agree", 'no' );
		update_option( "{$this->prefix}_other", 'old' );
		$spec                            = $this->spec(
			array(
				$this->field( 'text', 'name', array( 'custom_attributes' => array( 'disabled' => 'disabled' ) ) ),
				$this->field( 'checkbox', 'agree', array( 'disabled' => true ) ),
				$this->field( 'text', 'other' ),
			)
		);
		$data                            = self::form_data( $this->open( $spec, $this->admin )['body'] );
		$data[ "{$this->prefix}_name" ]  = 'tampered';
		$data[ "{$this->prefix}_agree" ] = 'yes';

		$this->save( $spec, $this->admin, $data );

		self::assertSame( array( 'old', 'no', 'old' ), $this->stored( 'name', 'agree', 'other' ) );
	}

	public function test_the_submenu_entry_and_the_page_exist_only_for_users_holding_the_capability(): void {
		$spec = $this->spec( array( $this->field( 'text', 'name' ) ), array( 'parent' => 'tools.php' ) );
		$link = '#menu-tools a[href$="?page=' . $this->id . '"]';

		$admin_menu  = $this->admin_request( 'GET', 'tools.php', $this->admin, self::FIXTURE, $spec )['body'];
		$editor_menu = $this->admin_request( 'GET', 'tools.php', $this->editor, self::FIXTURE, $spec )['body'];
		$editor_page = $this->open( $spec, $this->editor );

		self::assertSame( 'Acme', self::document( $admin_menu )->querySelector( $link )?->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		self::assertNull( self::document( $editor_menu )->querySelector( $link ) );
		self::assertSame( 403, $editor_page['died']['status'] ?? null );
		self::assertStringNotContainsString( 'Acme settings', $editor_page['body'] );
	}

	public function test_a_form_posted_back_with_one_edit_changes_only_that_field_and_labels_point_at_single_controls(): void {
		$stored = array(
			'name'   => 'old',
			'count'  => '3',
			'email'  => 'ops@example.com',
			'site'   => 'https://example.com/a?b=c&d=e',
			'notes'  => "One\nTwo",
			'on'     => 'yes',
			'off'    => 'no',
			'size'   => 'blue',
			'tone'   => '2',
			'colors' => array( '1', '3' ),
			'days'   => array( '2' ),
			'agree'  => 'yes',
		);
		foreach ( $stored as $name => $value ) {
			update_option( "{$this->prefix}_$name", $value );
		}
		$spec = $this->spec(
			array(
				$this->field( 'text', 'name' ),
				$this->field( 'number', 'count' ),
				$this->field( 'email', 'email' ),
				$this->field( 'url', 'site' ),
				$this->field( 'textarea', 'notes' ),
				$this->field( 'checkbox', 'on' ),
				$this->field( 'checkbox', 'off' ),
				$this->field( 'select', 'size', array( 'options' => self::COLORS ) ),
				$this->field( 'radio', 'tone', array( 'options' => self::NUMBERED ) ),
				$this->field( 'multiselect', 'colors', array( 'options' => self::NUMBERED ) ),
				$this->field( 'checkboxes', 'days', array( 'options' => self::NUMBERED ) ),
				$this->field( 'checkbox', 'agree', array( 'desc' => 'I agree' ) ),
			)
		);
		$form = $this->open( $spec, $this->admin )['body'];

		$this->save( $spec, $this->admin, self::form_data( $form, array( "{$this->prefix}_name" => 'new' ) ) );

		self::assertSame( \array_values( \array_replace( $stored, array( 'name' => 'new' ) ) ), $this->stored( ...\array_keys( $stored ) ) );
		$labels = array();
		foreach ( self::document( $form )->querySelectorAll( 'form tr' ) as $row ) {
			$for = $row->querySelector( 'th label' )?->getAttribute( 'for' );
			if ( \is_string( $for ) ) {
				self::assertNotNull( $row->querySelector( 'td' )?->querySelector( '[id="' . $for . '"]' ) );
			}
			$labels[ \trim( (string) $row->querySelector( 'th' )?->textContent ) ] = $for; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		}
		self::assertSame(
			array(
				'Name'   => "{$this->prefix}_name",
				'Count'  => "{$this->prefix}_count",
				'Email'  => "{$this->prefix}_email",
				'Site'   => "{$this->prefix}_site",
				'Notes'  => "{$this->prefix}_notes",
				'On'     => "{$this->prefix}_on",
				'Off'    => "{$this->prefix}_off",
				'Size'   => "{$this->prefix}_size",
				'Tone'   => null,
				'Colors' => "{$this->prefix}_colors",
				'Days'   => null,
				'Agree'  => null,
			),
			$labels
		);
	}

	public function test_a_submitted_id_outside_the_tab_is_not_stored(): void {
		$spec = $this->spec( array( $this->field( 'text', 'name' ) ) );
		$data = self::form_data( $this->open( $spec, $this->admin )['body'], array( "{$this->prefix}_name" => 'new' ) ) + array( "{$this->prefix}_injected" => 'x' );

		$this->save( $spec, $this->admin, $data );

		self::assertSame( 'new', get_option( "{$this->prefix}_name" ) );
		self::assertFalse( get_option( "{$this->prefix}_injected" ) );
	}

	public function test_tabs_and_fields_contributed_through_the_filters_render_and_save(): void {
		$spec = $this->spec(
			array( $this->field( 'text', 'name' ) ),
			array(),
			array(
				'contributed' => array(
					'tabs'   => array( self::tab( 'addon' ) ),
					'fields' => array(
						'general' => array( $this->field( 'text', 'extra', array( 'validate' => 'acme:append' ) ) ),
						'addon'   => array( $this->field( 'text', 'key' ) ),
					),
				),
			)
		);

		$general = $this->open( $spec, $this->admin )['body'];
		$this->save( $spec, $this->admin, self::form_data( $general, array( "{$this->prefix}_extra" => 'e' ) ) );
		$this->save( $spec, $this->admin, self::form_data( $this->open( $spec, $this->admin, 'addon' )['body'], array( "{$this->prefix}_key" => 'k' ) ) );

		self::assertSame( array( 'General', 'Addon' ), self::tab_titles( $general ) );
		self::assertSame( array( '', 'e!', 'k' ), $this->stored( 'name', 'extra', 'key' ) );
	}

	public function test_each_tab_shows_its_own_form_and_an_unknown_tab_shows_the_first(): void {
		$spec = $this->spec(
			array( $this->field( 'text', 'name' ) ),
			array( 'tabs' => array( self::tab( 'general' ), self::tab( 'second' ) ) ),
			array( 'fields' => array( 'second' => array( $this->field( 'text', 'other' ) ) ) )
		);

		$second     = $this->open( $spec, $this->admin, 'second' )['body'];
		$second_tab = self::document( $second );
		$unknown    = self::document( $this->open( $spec, $this->admin, 'missing' )['body'] );
		$single     = self::document( $this->open( $this->spec( array( $this->field( 'text', 'name' ) ) ), $this->admin )['body'] );

		self::assertSame( "{$this->id}_second", $second_tab->querySelector( 'form input[name="option_page"]' )?->getAttribute( 'value' ) );
		self::assertSame( array( 'Other' ), self::row_titles( $second ) );
		self::assertSame( 'Second', $second_tab->querySelector( '.nav-tab[aria-current="page"]' )?->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		self::assertSame( $this->group, $unknown->querySelector( 'form input[name="option_page"]' )?->getAttribute( 'value' ) );
		self::assertSame( 'General', $unknown->querySelector( '.nav-tab[aria-current="page"]' )?->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		self::assertNull( $single->querySelector( '.nav-tab' ) );
	}

	public function test_saving_one_tab_leaves_the_other_tabs_fields_as_they_are(): void {
		update_option( "{$this->prefix}_name", 'first' );
		$spec = $this->spec(
			array( $this->field( 'text', 'name' ) ),
			array( 'tabs' => array( self::tab( 'general' ), self::tab( 'second' ) ) ),
			array( 'fields' => array( 'second' => array( $this->field( 'text', 'other' ) ) ) )
		);

		$saved = $this->save( $spec, $this->admin, self::form_data( $this->open( $spec, $this->admin, 'second' )['body'], array( "{$this->prefix}_other" => 'second' ) ) );

		self::assertNull( $saved['died'] );
		self::assertSame( array( 'first', 'second' ), $this->stored( 'name', 'other' ) );
	}

	protected function spec( array $fields, array $page = array(), array $extra = array() ): array {
		return \array_replace_recursive(
			array(
				'id'     => $this->id,
				'slug'   => $this->slug,
				'prefix' => $this->prefix,
				'page'   => $page + array(
					'parent'     => 'options-general.php',
					'capability' => 'manage_options',
					'page_title' => 'Acme settings',
					'menu_title' => 'Acme',
					'tabs'       => array( self::tab( 'general' ) ),
				),
				'fields' => array( 'general' => $fields ),
			),
			$extra
		);
	}

	protected function open( array $spec, int $user_id, string $tab = '' ): array {
		$parent_slug = $spec['page']['parent'];
		$file        = \str_ends_with( $parent_slug, '.php' ) ? $parent_slug : 'admin.php';

		return $this->admin_request( 'GET', "$file?page=$this->id" . ( '' === $tab ? '' : "&tab=$tab" ), $user_id, self::FIXTURE, $spec );
	}

	protected function save( array $spec, int $user_id, array $data, string $query = '' ): array {
		return $this->admin_request( 'POST', "options.php$query", $user_id, self::FIXTURE, $spec, $data );
	}

	protected function follow( array $spec, int $user_id, array $saved ): array {
		self::assertIsString( $saved['location'] );

		return $this->admin_request( 'GET', \explode( '/wp-admin/', $saved['location'], 2 )[1], $user_id, self::FIXTURE, $spec );
	}

	// Reads each named field's row, as get_option() returns it.
	protected function stored( string ...$names ): array {
		return \array_map( fn ( string $name ): mixed => get_option( "{$this->prefix}_$name" ), $names );
	}

	protected static function tab( string $slug ): array {
		return array(
			'slug'  => $slug,
			'title' => \ucfirst( $slug ),
		);
	}

	protected function field( string $type, string $name, array $keys = array() ): array {
		return $keys + array(
			'type'  => $type,
			'id'    => "{$this->prefix}_$name",
			'title' => \ucfirst( $name ),
		);
	}

	protected static function form_data( string $html, array $changes = array() ): array {
		return self::serialize_form( $html, 'form[action="options.php"]', $changes );
	}

	protected static function notices( string $html ): array {
		$notices = array();
		foreach ( self::document( $html )->querySelectorAll( '.settings-error' ) as $notice ) {
			$notices[] = \trim( $notice->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		}

		return $notices;
	}

	protected static function row_titles( string $html ): array {
		$titles = array();
		foreach ( self::document( $html )->querySelectorAll( 'form tr th' ) as $title ) {
			$titles[] = \trim( $title->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		}

		return $titles;
	}

	protected static function tab_titles( string $html ): array {
		$titles = array();
		foreach ( self::document( $html )->querySelectorAll( '.nav-tab' ) as $tab ) {
			$titles[] = \trim( $tab->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		}

		return $titles;
	}
}
