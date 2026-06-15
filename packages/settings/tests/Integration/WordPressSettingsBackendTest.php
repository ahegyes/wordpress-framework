<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Tests\Integration;

use DeepWebSolutions\Framework\Settings\Exceptions\DuplicateSettingsFieldException;
use DeepWebSolutions\Framework\Settings\Exceptions\DuplicateSettingsSectionException;
use DeepWebSolutions\Framework\Settings\ValueObjects\SettingsField;
use DeepWebSolutions\Framework\Settings\ValueObjects\SettingsPage;
use DeepWebSolutions\Framework\Settings\ValueObjects\SettingsSection;
use DeepWebSolutions\Framework\Settings\WordPressSettingsBackend;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( WordPressSettingsBackend::class )]
#[UsesClass( SettingsField::class )]
#[UsesClass( SettingsSection::class )]
#[UsesClass( SettingsPage::class )]
final class WordPressSettingsBackendTest extends TestCase {
	private const SLUG            = 'dws-test-settings';
	private const GENERAL_OPTION  = 'dws-test-settings-general';
	private const ADVANCED_OPTION = 'dws-test-settings-advanced';

	protected function setUp(): void {
		parent::setUp();

		\wp_set_current_user( 1 );
		\remove_all_actions( 'admin_menu' );
		\remove_all_actions( 'admin_init' );
		\remove_all_filters( 'sanitize_option_' . self::GENERAL_OPTION );
		\remove_all_filters( 'sanitize_option_' . self::ADVANCED_OPTION );
		unset( $GLOBALS['_parent_pages'][ self::SLUG ] );
		$this->clean();
	}

	protected function tearDown(): void {
		$this->clean();
		parent::tearDown();
	}

	public function test_registers_a_submenu_under_the_settings_parent(): void {
		$this->register( $this->page() );
		\do_action( 'admin_menu' );

		self::assertSame( 'options-general.php', $GLOBALS['_parent_pages'][ self::SLUG ] ?? null );
	}

	public function test_the_menu_title_is_escaped_for_the_admin_menu(): void {
		$GLOBALS['submenu'] = array();
		$page               = new SettingsPage(
			slug: self::SLUG,
			page_title: 'DWS Test',
			menu_title: '<script>alert(1)</script>',
			capability: 'edit_pages',
			sections: array(
				new SettingsSection( 'general', 'General', array( new SettingsField( id: 'a', type: 'text', label: 'A' ) ) ),
			),
		);
		$this->register( $page );
		\do_action( 'admin_menu' );

		global $submenu;
		$entries = \is_array( $submenu ) && isset( $submenu['options-general.php'] ) && \is_array( $submenu['options-general.php'] )
			? $submenu['options-general.php']
			: array();
		$title = '';
		foreach ( $entries as $entry ) {
			if ( \is_array( $entry ) && self::SLUG === ( $entry[2] ?? null ) ) {
				$title = (string) ( $entry[0] ?? '' );
			}
		}

		// WordPress prints the submenu title only wptexturized, so the backend must hand it pre-escaped.
		self::assertStringNotContainsString( '<script>', $title );
		self::assertStringContainsString( '&lt;script&gt;', $title );
	}

	public function test_registers_one_setting_per_section_on_admin_init(): void {
		$this->register( $this->page() );
		\do_action( 'admin_init' );

		$registered = \get_registered_settings();
		self::assertArrayHasKey( self::GENERAL_OPTION, $registered );
		self::assertArrayHasKey( self::ADVANCED_OPTION, $registered );
	}

	public function test_wires_the_option_page_capability_to_the_page_capability(): void {
		$this->register( $this->page() );
		\do_action( 'admin_init' );

		self::assertSame( 'edit_pages', \apply_filters( 'option_page_capability_' . self::GENERAL_OPTION, 'manage_options' ) );
	}

	public function test_sets_and_gets_a_value_routed_to_its_section_option(): void {
		$backend = $this->register( $this->page() );

		$backend->set( 'site_name', 'Acme' );

		self::assertSame( 'Acme', $backend->get( 'site_name' ) );
		self::assertSame( 'Acme', \get_option( self::GENERAL_OPTION )['site_name'] ?? null );
	}

	public function test_routes_fields_to_the_section_that_declares_them(): void {
		$backend = $this->register( $this->page() );

		$backend->set( 'site_name', 'Acme' );
		$backend->set( 'cache_ttl', 60 );

		self::assertArrayHasKey( 'site_name', \get_option( self::GENERAL_OPTION ) );
		self::assertArrayHasKey( 'cache_ttl', \get_option( self::ADVANCED_OPTION ) );
		self::assertArrayNotHasKey( 'cache_ttl', \get_option( self::GENERAL_OPTION ) );
	}

	public function test_a_stored_null_is_distinct_from_an_absent_value(): void {
		$backend = $this->register( $this->page() );

		$backend->set( 'site_name', null );

		self::assertTrue( $backend->has( 'site_name' ) );
		self::assertNull( $backend->get( 'site_name', 'sentinel' ) );
		self::assertFalse( $backend->has( 'enabled' ) );
		self::assertSame( 'sentinel', $backend->get( 'enabled', 'sentinel' ) );
	}

	public function test_delete_reports_whether_a_value_existed(): void {
		$backend = $this->register( $this->page() );
		$backend->set( 'site_name', 'Acme' );

		self::assertTrue( $backend->delete( 'site_name' ) );
		self::assertFalse( $backend->has( 'site_name' ) );
		self::assertFalse( $backend->delete( 'site_name' ) );
	}

	public function test_does_not_touch_unrelated_options(): void {
		\update_option( 'dws_unrelated_option', 'keep-me' );
		$backend = $this->register( $this->page() );

		$backend->set( 'site_name', 'Acme' );
		$backend->delete( 'site_name' );

		self::assertSame( 'keep-me', \get_option( 'dws_unrelated_option' ) );
		\delete_option( 'dws_unrelated_option' );
	}

	public function test_a_form_save_processes_fields_and_coerces_an_absent_checkbox_to_false(): void {
		$this->register( $this->page() );
		\do_action( 'admin_init' );

		// The form submits site_name but omits the unchecked enabled checkbox.
		$this->form_save( self::GENERAL_OPTION, array( 'site_name' => 'Acme' ) );

		$stored = \get_option( self::GENERAL_OPTION );
		self::assertSame( 'Acme', $stored['site_name'] );
		self::assertFalse( $stored['enabled'] );
	}

	public function test_a_field_capability_preserves_a_protected_field_against_a_user_without_it(): void {
		$page    = new SettingsPage(
			slug: self::SLUG,
			page_title: 'DWS Test',
			menu_title: 'DWS Test',
			capability: 'edit_pages',
			sections: array(
				new SettingsSection(
					'general',
					'General',
					array(
						new SettingsField( id: 'site_name', type: 'text', label: 'Site Name' ),
						new SettingsField( id: 'secret', type: 'text', label: 'Secret', capability: 'dws_protected_cap' ),
					),
				),
			),
		);
		$backend = $this->register( $page );
		\do_action( 'admin_init' );

		// Seed both fields programmatically (passes through the guard).
		$backend->set( 'site_name', 'before' );
		$backend->set( 'secret', 'classified' );

		// User 1 lacks dws_protected_cap; a form save tries to change both fields.
		$this->form_save( self::GENERAL_OPTION, array( 'site_name' => 'after', 'secret' => 'tampered' ) );

		self::assertSame( 'after', $backend->get( 'site_name' ) );
		self::assertSame( 'classified', $backend->get( 'secret' ) );
	}

	public function test_a_scalar_submission_is_processed_not_stored_raw(): void {
		$page = new SettingsPage(
			slug: self::SLUG,
			page_title: 'DWS Test',
			menu_title: 'DWS Test',
			capability: 'edit_pages',
			sections: array(
				new SettingsSection(
					'general',
					'General',
					array(
						new SettingsField( id: 'site_name', type: 'text', label: 'Site Name' ),
						new SettingsField( id: 'secret', type: 'text', label: 'Secret', capability: 'dws_protected_cap' ),
					),
				),
			),
		);
		$backend = $this->register( $page );
		\do_action( 'admin_init' );
		$backend->set( 'secret', 'classified' );

		// A non-array submission (a scalar) is processed into a clean array, not stored raw; the protected
		// field is preserved because the current user lacks its capability.
		\update_option( self::GENERAL_OPTION, 'tampered' );

		self::assertIsArray( \get_option( self::GENERAL_OPTION ) );
		self::assertSame( 'classified', $backend->get( 'secret' ) );
	}

	public function test_a_page_capability_the_user_lacks_hides_the_submenu(): void {
		$page = new SettingsPage(
			slug: self::SLUG,
			page_title: 'DWS Test',
			menu_title: 'DWS Test',
			capability: 'dws_protected_cap',
			sections: array(
				new SettingsSection( 'general', 'General', array( new SettingsField( id: 'site_name', type: 'text', label: 'Site Name' ) ) ),
			),
		);
		$this->register( $page );
		\do_action( 'admin_menu' );

		self::assertArrayNotHasKey( self::SLUG, $GLOBALS['_parent_pages'] ?? array() );
	}

	public function test_a_programmatic_set_passes_through_the_registered_sanitizer_untouched(): void {
		$backend = $this->register( $this->page() );
		\do_action( 'admin_init' );

		// With the sanitize_option filter registered, a programmatic write must not be re-coerced:
		// a stored null stays null, and writing one field does not force a sibling checkbox to false.
		$backend->set( 'enabled', true );
		$backend->set( 'site_name', null );

		self::assertNull( $backend->get( 'site_name', 'sentinel' ) );
		self::assertTrue( $backend->get( 'enabled' ) );
	}

	public function test_a_duplicate_field_id_across_sections_throws(): void {
		$page = new SettingsPage(
			slug: self::SLUG,
			page_title: 'DWS Test',
			menu_title: 'DWS Test',
			capability: 'edit_pages',
			sections: array(
				new SettingsSection( 'general', 'General', array( new SettingsField( id: 'dup', type: 'text', label: 'A' ) ) ),
				new SettingsSection( 'advanced', 'Advanced', array( new SettingsField( id: 'dup', type: 'text', label: 'B' ) ) ),
			),
		);

		$this->expectException( DuplicateSettingsFieldException::class );

		( new WordPressSettingsBackend() )->register_page( $page );
	}

	public function test_a_duplicate_section_id_on_a_page_throws(): void {
		$page = new SettingsPage(
			slug: self::SLUG,
			page_title: 'DWS Test',
			menu_title: 'DWS Test',
			capability: 'edit_pages',
			sections: array(
				new SettingsSection( 'general', 'General', array( new SettingsField( id: 'a', type: 'text', label: 'A' ) ) ),
				new SettingsSection( 'general', 'General Again', array( new SettingsField( id: 'b', type: 'text', label: 'B' ) ) ),
			),
		);

		$this->expectException( DuplicateSettingsSectionException::class );

		( new WordPressSettingsBackend() )->register_page( $page );
	}

	public function test_a_programmatic_delete_bypasses_the_registered_sanitizer(): void {
		$backend = $this->register( $this->page() );
		\do_action( 'admin_init' );
		$backend->set( 'enabled', true );

		// delete() must bypass the sanitizer, else the now-absent enabled checkbox would be re-coerced to false.
		self::assertTrue( $backend->delete( 'enabled' ) );
		self::assertFalse( $backend->has( 'enabled' ) );
	}

	public function test_a_nested_programmatic_write_does_not_cause_the_outer_write_to_be_processed(): void {
		$backend     = $this->register( $this->page() );
		\do_action( 'admin_init' );
		$nested_done = false;

		// A filter performs a nested backend write during the outer write's sanitize chain. A naive bool
		// flag would be cleared by the nested write, coercing the outer null to false; the depth counter
		// keeps the outer write flagged so it passes through.
		\add_filter(
			'sanitize_option_' . self::GENERAL_OPTION,
			static function ( mixed $value ) use ( $backend, &$nested_done ): mixed {
				if ( ! $nested_done ) {
					$nested_done = true;
					$backend->set( 'enabled', true );
				}

				return $value;
			},
			1,
		);

		$backend->set( 'site_name', null );

		self::assertNull( $backend->get( 'site_name', 'sentinel' ) );
	}

	private function register( SettingsPage $page ): WordPressSettingsBackend {
		$backend = new WordPressSettingsBackend();
		$backend->register_page( $page );

		return $backend;
	}

	/**
	 * Simulates the options.php save of $data for the given option group.
	 *
	 * @param array<string, mixed> $data
	 */
	private function form_save( string $option, array $data ): void {
		// A write the backend did not initiate — as options.php performs — is processed by the sanitizer.
		\update_option( $option, $data );
	}

	private function page(): SettingsPage {
		return new SettingsPage(
			slug: self::SLUG,
			page_title: 'DWS Test',
			menu_title: 'DWS Test',
			capability: 'edit_pages',
			sections: array(
				new SettingsSection(
					'general',
					'General',
					array(
						new SettingsField( id: 'site_name', type: 'text', label: 'Site Name' ),
						new SettingsField( id: 'enabled', type: 'checkbox', label: 'Enabled' ),
					),
				),
				new SettingsSection(
					'advanced',
					'Advanced',
					array( new SettingsField( id: 'cache_ttl', type: 'number', label: 'Cache TTL' ) ),
				),
			),
		);
	}

	private function clean(): void {
		\delete_option( self::GENERAL_OPTION );
		\delete_option( self::ADVANCED_OPTION );
		\delete_option( 'dws_unrelated_option' );
	}
}
