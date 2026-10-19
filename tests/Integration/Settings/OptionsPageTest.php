<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Settings;

use DeepWebSolutions\Framework\Settings\OptionsPage;
use DeepWebSolutions\Framework\Shared\Exception\InvalidArgumentException;
use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesStorageTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\ProvidesHelperIdsTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\RunsAdminRequestsTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( OptionsPage::class )]
final class OptionsPageTest extends TestCase {
	use CreatesUsersTrait;
	use IsolatesHooksTrait;
	use IsolatesStorageTrait;
	use ProvidesHelperIdsTrait;
	use RunsAdminRequestsTrait;

	protected const string FIXTURE = __DIR__ . '/../../Fixtures/Settings/acme-options-page.php';

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
	protected string $option;

	#[\Override]
	protected function setUp(): void {
		$this->admin  = $this->make_user( 'acme_options_admin', 'administrator' );
		$this->editor = $this->make_user( 'acme_options_editor', 'editor' );
		$this->id     = "{$this->prefix}_settings";
		$this->option = "{$this->prefix}_general";
	}

	#[\Override]
	protected function tearDown(): void {
		\delete_transient( 'settings_errors' );
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

		new OptionsPage( $id, static fn (): array => array() );
	}

	#[DataProvider( 'v1_helper_ids' )]
	public function test_every_v1_key_is_a_valid_id( string $id ): void {
		self::assertInstanceOf( OptionsPage::class, new OptionsPage( $id, static fn (): array => array() ) );
	}

	public function test_the_definition_is_evaluated_neither_at_construction_nor_at_registration(): void {
		$calls = 0;
		$page  = new OptionsPage(
			$this->id,
			static function () use ( &$calls ): array {
				++$calls;

				return array();
			}
		);

		$page->register_hooks();

		self::assertSame( 0, $calls );
	}

	public function test_a_first_save_validates_once_and_stores_the_result_without_an_error(): void {
		$spec = $this->spec( array( self::field( 'text', 'name', array( 'validate' => 'acme:append' ) ) ) );
		$data = self::form_data( $this->open( $spec, $this->admin )['body'], array( "{$this->option}[name]" => 'v' ) );

		$saved = $this->save( $spec, $this->admin, $data );

		self::assertSame( array( 'name' => 'v!' ), \get_option( $this->option ) );
		self::assertSame( array( array( 'name', 'v' ) ), $saved['records'] );
		self::assertSame( array( 'Settings saved.' ), self::notices( $this->follow( $spec, $this->admin, $saved )['body'] ) );
	}

	public function test_a_programmatic_update_outside_options_php_is_stored_as_given(): void {
		$value = array( 'name' => ' <b>raw</b> ' );
		$spec  = $this->spec( array( self::field( 'text', 'name', array( 'validate' => 'acme:append' ) ) ), array(), array( 'update' => array( $this->option => $value ) ) );

		$request = $this->admin_request( 'GET', "admin-post.php?option_page={$this->option}", $this->admin, self::FIXTURE, $spec );

		self::assertSame( $value, \get_option( $this->option ) );
		self::assertSame( array(), $request['records'] );
	}

	#[DataProvider( 'parents' )]
	public function test_a_rejected_field_keeps_its_value_and_its_message_shows_once( string $parent_file ): void {
		\update_option( $this->option, array( 'name' => 'old' ) );
		$spec = $this->spec( array( self::field( 'text', 'name', array( 'validate' => 'acme:reject' ) ) ), array( 'parent' => $parent_file ) );
		$data = self::form_data( $this->open( $spec, $this->admin )['body'], array( "{$this->option}[name]" => 'too long' ) );

		$saved = $this->save( $spec, $this->admin, $data );

		self::assertSame( array( 'name' => 'old' ), \get_option( $this->option ) );
		self::assertSame( array( 'Name was not saved, so it keeps its previous value. Use at most four characters.' ), self::notices( $this->follow( $spec, $this->admin, $saved )['body'] ) );
	}

	#[DataProvider( 'parents' )]
	public function test_a_valid_save_shows_settings_saved_once( string $parent_file ): void {
		$spec = $this->spec( array( self::field( 'text', 'name' ) ), array( 'parent' => $parent_file ) );
		$data = self::form_data( $this->open( $spec, $this->admin )['body'], array( "{$this->option}[name]" => 'new' ) );

		$saved = $this->save( $spec, $this->admin, $data );

		self::assertSame( array( 'name' => 'new' ), \get_option( $this->option ) );
		self::assertSame( array( 'Settings saved.' ), self::notices( $this->follow( $spec, $this->admin, $saved )['body'] ) );
	}

	public function test_a_rejection_without_a_message_says_that_the_value_is_not_valid(): void {
		\update_option( $this->option, array( 'name' => 'old' ) );
		$spec = $this->spec( array( self::field( 'text', 'name', array( 'validate' => 'acme:reject-silently' ) ) ) );
		$data = self::form_data( $this->open( $spec, $this->admin )['body'], array( "{$this->option}[name]" => 'new' ) );

		$saved = $this->save( $spec, $this->admin, $data );

		self::assertSame( array( 'name' => 'old' ), \get_option( $this->option ) );
		self::assertSame( array( 'Name was not saved because its value is not valid, so it keeps its previous value.' ), self::notices( $this->follow( $spec, $this->admin, $saved )['body'] ) );
	}

	public function test_a_built_in_rejection_carries_what_to_enter(): void {
		\update_option( $this->option, array( 'email' => 'ops@example.com' ) );
		$spec = $this->spec( array( self::field( 'email', 'email' ) ) );
		$data = self::form_data( $this->open( $spec, $this->admin )['body'], array( "{$this->option}[email]" => 'not an email' ) );

		$saved = $this->save( $spec, $this->admin, $data );

		self::assertSame( array( 'email' => 'ops@example.com' ), \get_option( $this->option ) );
		self::assertSame( array( 'Email was not saved, so it keeps its previous value. Enter a valid email address, such as name@example.com.' ), self::notices( $this->follow( $spec, $this->admin, $saved )['body'] ) );
	}

	public function test_titles_and_messages_print_as_text_and_an_untitled_field_is_named_by_its_id(): void {
		$spec = $this->spec(
			array(
				self::field( 'email', 'email', array( 'title' => '<em>Email</em>' ) ),
				array(
					'type' => 'url',
					'id'   => 'site',
				),
			)
		);
		$form = $this->open( $spec, $this->admin )['body'];
		$data = self::form_data(
			$form,
			array(
				"{$this->option}[email]" => 'not an email',
				"{$this->option}[site]"  => 'javascript:alert(1)',
			)
		);

		$page = $this->follow( $spec, $this->admin, $this->save( $spec, $this->admin, $data ) )['body'];

		self::assertSame( array( '<em>Email</em>', '' ), self::row_titles( $form ) );
		self::assertNull( self::document( $form )->querySelector( 'form em' ) );
		self::assertSame(
			array(
				'<em>Email</em> was not saved, so it keeps its previous value. Enter a valid email address, such as name@example.com.',
				'site was not saved, so it keeps its previous value. Enter a valid URL, such as https://example.com.',
			),
			self::notices( $page )
		);
		self::assertNull( self::document( $page )->querySelector( '.settings-error em' ) );
	}

	#[DataProvider( 'multi_value_types' )]
	public function test_a_fully_cleared_multi_value_field_stores_an_empty_array( string $type ): void {
		\update_option( $this->option, array( 'colors' => array( 'red', 'blue' ) ) );
		$spec = $this->spec( array( self::field( $type, 'colors', array( 'options' => self::COLORS ) ) ) );
		$data = self::form_data( $this->open( $spec, $this->admin )['body'], array( "{$this->option}[colors]" => array() ) );

		$this->save( $spec, $this->admin, $data );

		self::assertSame( array( 'colors' => array() ), \get_option( $this->option ) );
	}

	public function test_an_unchecked_checkbox_stores_no_while_a_text_field_posts(): void {
		\update_option(
			$this->option,
			array(
				'alpha' => 'yes',
				'beta'  => 'yes',
				'name'  => 'old',
			)
		);
		$spec = $this->spec( array( self::field( 'checkbox', 'alpha' ), self::field( 'checkbox', 'beta' ), self::field( 'text', 'name' ) ) );
		$data = self::form_data(
			$this->open( $spec, $this->admin )['body'],
			array(
				"{$this->option}[beta]" => false,
				"{$this->option}[name]" => 'new',
			)
		);

		$this->save( $spec, $this->admin, $data );

		self::assertSame(
			array(
				'alpha' => 'yes',
				'beta'  => 'no',
				'name'  => 'new',
			),
			\get_option( $this->option )
		);
	}

	public function test_a_disabled_field_keeps_its_value_and_renders_no_hidden_input(): void {
		$stored = array(
			'name'   => 'old',
			'agree'  => 'yes',
			'colors' => array( 'red' ),
			'other'  => 'old',
		);
		\update_option( $this->option, $stored );
		$spec = $this->spec(
			array(
				self::field( 'text', 'name', array( 'custom_attributes' => array( 'disabled' => 'disabled' ) ) ),
				self::field( 'checkbox', 'agree', array( 'disabled' => true ) ),
				self::field(
					'multiselect',
					'colors',
					array(
						'options'           => self::COLORS,
						'custom_attributes' => array( 'disabled' => 'disabled' ),
					)
				),
				self::field( 'text', 'other' ),
			)
		);
		$form = $this->open( $spec, $this->admin )['body'];

		$this->save( $spec, $this->admin, self::form_data( $form, array( "{$this->option}[other]" => 'new' ) ) );

		self::assertSame( \array_replace( $stored, array( 'other' => 'new' ) ), \get_option( $this->option ) );
		self::assertSame( array( 'other' ), \array_keys( self::form_data( $form )[ $this->option ] ) );
		self::assertCount( 0, self::document( $form )->querySelectorAll( 'form input[type="hidden"][name^="' . $this->option . '["]' ) );
	}

	public function test_a_tab_whose_every_control_is_disabled_leaves_the_row_unchanged(): void {
		\update_option( $this->option, array( 'name' => 'old' ) );
		$spec = $this->spec(
			array(
				self::field(
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

		self::assertArrayNotHasKey( $this->option, $data );
		self::assertSame( array( 'name' => 'old' ), \get_option( $this->option ) );
		self::assertSame( array(), $saved['records'] );
	}

	public function test_a_capability_above_manage_options_refuses_an_administrators_save(): void {
		$spec = $this->spec( array( self::field( 'text', 'name' ) ), array( 'capability' => 'acme_manage_settings' ) );
		$data = array(
			'option_page' => $this->option,
			'action'      => 'update',
			$this->option => array( 'name' => 'new' ),
		);

		$saved = $this->save( $spec, $this->admin, $data );

		self::assertSame( 403, $saved['died']['status'] ?? null );
		self::assertStringContainsString( 'Sorry, you are not allowed to manage options for this site.', $saved['died']['message'] ?? '' );
		self::assertFalse( \get_option( $this->option ) );
	}

	public function test_a_capability_below_manage_options_lets_its_holder_save(): void {
		$spec = $this->spec( array( self::field( 'text', 'name' ) ), array( 'capability' => 'edit_pages' ) );
		$data = self::form_data( $this->open( $spec, $this->editor )['body'], array( "{$this->option}[name]" => 'new' ) );

		$this->save( $spec, $this->editor, $data );

		self::assertSame( array( 'name' => 'new' ), \get_option( $this->option ) );
	}

	#[DataProvider( 'relocated_option_pages' )]
	public function test_an_option_page_outside_the_post_body_or_padded_still_validates( string $variant ): void {
		\update_option( $this->option, array( 'size' => 's' ) );
		$spec                          = $this->spec(
			array(
				self::field(
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
		$data                          = self::form_data( $this->open( $spec, $this->admin )['body'] );
		$data[ $this->option ]['size'] = 'xl';
		$query                         = '';
		if ( 'query-string' === $variant ) {
			unset( $data['option_page'] );
			$query = '?option_page=' . \rawurlencode( $this->option );
		} else {
			$data['option_page'] = " {$this->option} ";
		}

		$saved = $this->save( $spec, $this->admin, $data, $query );

		self::assertNull( $saved['died'] );
		self::assertSame( array( 'size' => 's' ), \get_option( $this->option ) );
	}

	#[DataProvider( 'multi_value_types' )]
	public function test_two_selections_store_exactly_those_two( string $type ): void {
		$spec = $this->spec( array( self::field( $type, 'colors', array( 'options' => self::COLORS ) ) ) );
		$data = self::form_data( $this->open( $spec, $this->admin )['body'], array( "{$this->option}[colors]" => array( 'red', 'teal' ) ) );

		$this->save( $spec, $this->admin, $data );

		self::assertSame( array( 'colors' => array( 'red', 'teal' ) ), \get_option( $this->option ) );
	}

	#[DataProvider( 'multi_value_types' )]
	public function test_a_cleared_multi_value_field_reaches_validate_as_an_empty_array( string $type ): void {
		\update_option( $this->option, array( 'colors' => array( 'red' ) ) );
		$spec = $this->spec(
			array(
				self::field(
					$type,
					'colors',
					array(
						'options'  => self::COLORS,
						'validate' => 'acme:record',
					)
				),
			)
		);
		$data = self::form_data( $this->open( $spec, $this->admin )['body'], array( "{$this->option}[colors]" => array() ) );

		$saved = $this->save( $spec, $this->admin, $data );

		self::assertSame( array( array( 'colors', array() ) ), $saved['records'] );
		self::assertSame( array( 'colors' => array() ), \get_option( $this->option ) );
	}

	public function test_a_tampered_value_for_a_disabled_field_is_ignored(): void {
		$stored = array(
			'name'  => 'old',
			'agree' => 'no',
			'other' => 'old',
		);
		\update_option( $this->option, $stored );
		$spec                           = $this->spec(
			array(
				self::field( 'text', 'name', array( 'custom_attributes' => array( 'disabled' => 'disabled' ) ) ),
				self::field( 'checkbox', 'agree', array( 'disabled' => true ) ),
				self::field( 'text', 'other' ),
			)
		);
		$data                           = self::form_data( $this->open( $spec, $this->admin )['body'] );
		$data[ $this->option ]['name']  = 'tampered';
		$data[ $this->option ]['agree'] = 'yes';

		$this->save( $spec, $this->admin, $data );

		self::assertSame( $stored, \get_option( $this->option ) );
	}

	public function test_the_submenu_entry_and_the_page_exist_only_for_users_holding_the_capability(): void {
		$spec = $this->spec( array( self::field( 'text', 'name' ) ), array( 'parent' => 'tools.php' ) );
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
		\update_option( $this->option, $stored );
		$spec = $this->spec(
			array(
				self::field( 'text', 'name' ),
				self::field( 'number', 'count' ),
				self::field( 'email', 'email' ),
				self::field( 'url', 'site' ),
				self::field( 'textarea', 'notes' ),
				self::field( 'checkbox', 'on' ),
				self::field( 'checkbox', 'off' ),
				self::field( 'select', 'size', array( 'options' => self::COLORS ) ),
				self::field( 'radio', 'tone', array( 'options' => self::NUMBERED ) ),
				self::field( 'multiselect', 'colors', array( 'options' => self::NUMBERED ) ),
				self::field( 'checkboxes', 'days', array( 'options' => self::NUMBERED ) ),
				self::field( 'checkbox', 'agree', array( 'desc' => 'I agree' ) ),
			)
		);
		$form = $this->open( $spec, $this->admin )['body'];

		$this->save( $spec, $this->admin, self::form_data( $form, array( "{$this->option}[name]" => 'new' ) ) );

		self::assertSame( \array_replace( $stored, array( 'name' => 'new' ) ), \get_option( $this->option ) );
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
				'Name'   => "{$this->option}.name",
				'Count'  => "{$this->option}.count",
				'Email'  => "{$this->option}.email",
				'Site'   => "{$this->option}.site",
				'Notes'  => "{$this->option}.notes",
				'On'     => "{$this->option}.on",
				'Off'    => "{$this->option}.off",
				'Size'   => "{$this->option}.size",
				'Tone'   => null,
				'Colors' => "{$this->option}.colors",
				'Days'   => null,
				'Agree'  => null,
			),
			$labels
		);
	}

	public function test_a_submitted_id_outside_the_definition_is_not_stored(): void {
		$spec                              = $this->spec( array( self::field( 'text', 'name' ) ) );
		$data                              = self::form_data( $this->open( $spec, $this->admin )['body'], array( "{$this->option}[name]" => 'new' ) );
		$data[ $this->option ]['injected'] = 'x';

		$this->save( $spec, $this->admin, $data );

		self::assertSame( array( 'name' => 'new' ), \get_option( $this->option ) );
	}

	public function test_a_stored_id_absent_from_the_definition_keeps_its_value(): void {
		\update_option(
			$this->option,
			array(
				'name'    => 'old',
				'retired' => 'kept',
			)
		);
		$spec = $this->spec( array( self::field( 'text', 'name' ) ) );
		$data = self::form_data( $this->open( $spec, $this->admin )['body'], array( "{$this->option}[name]" => 'new' ) );

		$this->save( $spec, $this->admin, $data );

		self::assertSame(
			array(
				'name'    => 'new',
				'retired' => 'kept',
			),
			\get_option( $this->option )
		);
	}

	public function test_tabs_and_fields_contributed_through_the_tabs_filter_render_and_save(): void {
		$addon = "{$this->prefix}_addon";
		$spec  = $this->spec(
			array( self::field( 'text', 'name' ) ),
			array(),
			array(
				'contributed' => array(
					'fields' => array( 'general' => array( self::field( 'text', 'extra', array( 'validate' => 'acme:append' ) ) ) ),
					'tabs'   => array( self::tab( 'addon', $addon, array( self::field( 'text', 'key' ) ) ) ),
				),
			)
		);

		$general = $this->open( $spec, $this->admin )['body'];
		$this->save( $spec, $this->admin, self::form_data( $general, array( "{$this->option}[extra]" => 'e' ) ) );
		$this->save( $spec, $this->admin, self::form_data( $this->open( $spec, $this->admin, 'addon' )['body'], array( "{$addon}[key]" => 'k' ) ) );

		self::assertSame( array( 'General', 'Addon' ), self::tab_titles( $general ) );
		self::assertSame(
			array(
				'name'  => '',
				'extra' => 'e!',
			),
			\get_option( $this->option )
		);
		self::assertSame( array( 'key' => 'k' ), \get_option( $addon ) );
	}

	public function test_each_tab_shows_its_own_form_and_an_unknown_tab_shows_the_first(): void {
		$second = "{$this->prefix}_second";
		$spec   = $this->spec(
			array( self::field( 'text', 'name' ) ),
			array(
				'tabs' => array(
					self::tab( 'general', $this->option, array( self::field( 'text', 'name' ) ) ),
					self::tab( 'second', $second, array( self::field( 'text', 'other' ) ) ),
				),
			)
		);

		$second_tab = self::document( $this->open( $spec, $this->admin, 'second' )['body'] );
		$unknown    = self::document( $this->open( $spec, $this->admin, 'missing' )['body'] );
		$single     = self::document( $this->open( $this->spec( array( self::field( 'text', 'name' ) ) ), $this->admin )['body'] );

		self::assertSame( $second, $second_tab->querySelector( 'form input[name="option_page"]' )?->getAttribute( 'value' ) );
		self::assertSame( 'Second', $second_tab->querySelector( '.nav-tab[aria-current="page"]' )?->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		self::assertSame( $this->option, $unknown->querySelector( 'form input[name="option_page"]' )?->getAttribute( 'value' ) );
		self::assertSame( 'General', $unknown->querySelector( '.nav-tab[aria-current="page"]' )?->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		self::assertNull( $single->querySelector( '.nav-tab' ) );
	}

	protected function spec( array $fields, array $page = array(), array $extra = array() ): array {
		return array(
			'id'   => $this->id,
			'page' => $page + array(
				'parent'     => 'options-general.php',
				'capability' => 'manage_options',
				'page_title' => 'Acme settings',
				'menu_title' => 'Acme',
				'tabs'       => array( self::tab( 'general', $this->option, $fields ) ),
			),
		) + $extra;
	}

	protected function open( array $spec, int $user_id, string $tab = '' ): array {
		$parent_slug = $spec['page']['parent'];
		$file        = \str_ends_with( $parent_slug, '.php' ) ? $parent_slug : 'admin.php';

		return $this->admin_request( 'GET', "$file?page={$this->id}" . ( '' === $tab ? '' : "&tab=$tab" ), $user_id, self::FIXTURE, $spec );
	}

	protected function save( array $spec, int $user_id, array $data, string $query = '' ): array {
		return $this->admin_request( 'POST', "options.php$query", $user_id, self::FIXTURE, $spec, $data );
	}

	protected function follow( array $spec, int $user_id, array $saved ): array {
		self::assertIsString( $saved['location'] );

		return $this->admin_request( 'GET', \explode( '/wp-admin/', $saved['location'], 2 )[1], $user_id, self::FIXTURE, $spec );
	}

	protected static function tab( string $slug, string $option, array $fields ): array {
		return array(
			'slug'   => $slug,
			'title'  => \ucfirst( $slug ),
			'option' => $option,
			'fields' => $fields,
		);
	}

	protected static function field( string $type, string $id, array $keys = array() ): array {
		return $keys + array(
			'type'  => $type,
			'id'    => $id,
			'title' => \ucfirst( $id ),
		);
	}

	protected static function document( string $html ): \Dom\HTMLDocument {
		return \Dom\HTMLDocument::createFromString( $html, \LIBXML_NOERROR );
	}

	// Serializes the tab's form as a browser submits it once the user has set the named visible controls: enabled controls in document order, checked boxes and selected options only.
	protected static function form_data( string $html, array $changes = array() ): array {
		$pairs = array();
		foreach ( self::document( $html )->querySelectorAll( 'form[action="options.php"] [name]' ) as $control ) {
			$name    = (string) $control->getAttribute( 'name' );
			$type    = (string) $control->getAttribute( 'type' );
			$base    = \str_ends_with( $name, '[]' ) ? \substr( $name, 0, -2 ) : $name;
			$changed = 'hidden' !== $type && \array_key_exists( $base, $changes );
			$change  = $changed ? $changes[ $base ] : null;
			if ( $control->hasAttribute( 'disabled' ) || 'submit' === $type ) {
				continue;
			}

			if ( 'textarea' === $control->localName ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
				$pairs[] = array( $name, $changed ? $change : $control->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
			} elseif ( 'select' === $control->localName ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
				$selected = array();
				foreach ( $control->querySelectorAll( 'option' ) as $option ) {
					$value = (string) $option->getAttribute( 'value' );
					if ( $changed ? \in_array( $value, (array) $change, true ) : $option->hasAttribute( 'selected' ) ) {
						$selected[] = $value;
					}
				}
				if ( array() === $selected && ! $control->hasAttribute( 'multiple' ) ) {
					$selected[] = (string) $control->querySelector( 'option' )?->getAttribute( 'value' );
				}
				foreach ( $selected as $value ) {
					$pairs[] = array( $name, $value );
				}
			} elseif ( 'checkbox' === $type || 'radio' === $type ) {
				$value = (string) $control->getAttribute( 'value' );
				if ( $changed ? ( \is_bool( $change ) ? $change : \in_array( $value, (array) $change, true ) ) : $control->hasAttribute( 'checked' ) ) {
					$pairs[] = array( $name, $value );
				}
			} else {
				$pairs[] = array( $name, $changed ? $change : (string) $control->getAttribute( 'value' ) );
			}
		}

		\parse_str( \implode( '&', \array_map( static fn ( array $pair ): string => \rawurlencode( $pair[0] ) . '=' . \rawurlencode( $pair[1] ), $pairs ) ), $data );

		return $data;
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
