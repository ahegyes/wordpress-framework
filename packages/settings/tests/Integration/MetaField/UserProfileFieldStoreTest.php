<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Tests\Integration\MetaField;

use DeepWebSolutions\Framework\Settings\MetaField\MetaType;
use DeepWebSolutions\Framework\Settings\MetaField\MetadataRepository;
use DeepWebSolutions\Framework\Settings\MetaField\ObjectFieldForm;
use DeepWebSolutions\Framework\Settings\MetaField\UserProfileFieldStore;
use DeepWebSolutions\Framework\Settings\MetaField\ValueObjects\FieldGroup;
use DeepWebSolutions\Framework\Settings\MetaField\ValueObjects\UserProfileFieldGroup;
use DeepWebSolutions\Framework\Settings\Schema\Field\FieldProcessor;
use DeepWebSolutions\Framework\Settings\Schema\Field\FieldRenderer;
use DeepWebSolutions\Framework\Settings\Schema\Field\FieldType;
use DeepWebSolutions\Framework\Settings\Schema\Options\OptionsResolver;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsField;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( UserProfileFieldStore::class )]
#[UsesClass( ObjectFieldForm::class )]
#[UsesClass( MetadataRepository::class )]
#[UsesClass( MetaType::class )]
#[UsesClass( FieldGroup::class )]
#[UsesClass( UserProfileFieldGroup::class )]
#[UsesClass( SettingsField::class )]
#[UsesClass( FieldRenderer::class )]
#[UsesClass( FieldProcessor::class )]
#[UsesClass( OptionsResolver::class )]
#[UsesClass( FieldType::class )]
final class UserProfileFieldStoreTest extends TestCase {
	private const GROUP_ID     = 'dws_prefs';
	private const NONCE_NAME   = 'dws_object_field_dws_prefs_nonce';
	private const NONCE_ACTION = 'dws_object_field_dws_prefs';
	private const ISOLATED_HOOKS = array(
		'show_user_profile',
		'edit_user_profile',
		'personal_options_update',
		'edit_user_profile_update',
	);

	private int $user_id = 0;

	/**
	 * @var array<string, mixed>
	 */
	private array $saved_hooks = array();

	protected function setUp(): void {
		parent::setUp();

		require_once ABSPATH . 'wp-admin/includes/user.php';

		\wp_set_current_user( 1 );
		$_POST = array();

		global $wp_filter;
		foreach ( self::ISOLATED_HOOKS as $hook ) {
			$this->saved_hooks[ $hook ] = $wp_filter[ $hook ] ?? null;
			unset( $wp_filter[ $hook ] );
		}

		$user_id = \wp_insert_user(
			array( 'user_login' => 'dws_target_' . \uniqid(), 'user_pass' => 'x', 'role' => 'subscriber' ),
		);
		\assert( \is_int( $user_id ) );
		$this->user_id = $user_id;
	}

	protected function tearDown(): void {
		\wp_delete_user( $this->user_id );
		$_POST = array();

		global $wp_filter;
		foreach ( $this->saved_hooks as $hook => $saved ) {
			if ( null !== $saved ) {
				$wp_filter[ $hook ] = $saved;
			} else {
				unset( $wp_filter[ $hook ] );
			}
		}

		parent::tearDown();
	}

	public function test_editing_a_profile_renders_the_nonce_control_and_form_table(): void {
		( new UserProfileFieldStore() )->register( $this->profile() );

		\ob_start();
		\do_action( 'edit_user_profile', \get_userdata( $this->user_id ) );
		$html = (string) \ob_get_clean();

		self::assertStringContainsString( self::NONCE_NAME, $html );
		self::assertStringContainsString( 'name="dws_prefs[pref]"', $html );
		self::assertStringContainsString( 'class="form-table"', $html );
		self::assertStringContainsString( '<tr', $html );
	}

	public function test_saving_persists_with_capability_and_a_valid_nonce(): void {
		( new UserProfileFieldStore() )->register( $this->profile() );

		$_POST = array( self::NONCE_NAME => $this->nonce(), self::GROUP_ID => array( 'pref' => '1' ) );
		\do_action( 'edit_user_profile_update', $this->user_id );

		self::assertTrue( $this->repo()->has( $this->user_id, 'pref' ) );
	}

	public function test_saving_applies_the_builtin_default_sanitizer(): void {
		$raw = '<b>x</b>';
		( new UserProfileFieldStore() )->register( $this->text_profile() );

		$_POST = array( self::NONCE_NAME => $this->nonce(), self::GROUP_ID => array( 'pref' => $raw ) );
		\do_action( 'edit_user_profile_update', $this->user_id );

		self::assertSame( \sanitize_text_field( $raw ), $this->repo()->get( $this->user_id, 'pref' ) );
	}

	public function test_saving_preserves_an_existing_value_when_a_present_submission_is_invalid(): void {
		( new UserProfileFieldStore() )->register(
			$this->profile_with(
				new SettingsField( id: 'pref', type: 'select', label: 'Preference', options: array( 'red' => 'Red' ) ),
			),
		);

		$this->repo()->set( $this->user_id, 'pref', 'red' );
		$_POST = array( self::NONCE_NAME => $this->nonce(), self::GROUP_ID => array( 'pref' => 'blue' ) );
		\do_action( 'edit_user_profile_update', $this->user_id );

		self::assertSame( 'red', $this->repo()->get( $this->user_id, 'pref' ) );
	}

	public function test_saving_is_skipped_without_a_valid_nonce(): void {
		( new UserProfileFieldStore() )->register( $this->profile() );

		$_POST = array( self::GROUP_ID => array( 'pref' => '1' ) );
		\do_action( 'edit_user_profile_update', $this->user_id );

		self::assertFalse( $this->repo()->has( $this->user_id, 'pref' ) );
	}

	public function test_the_own_profile_surface_is_registered_by_default(): void {
		( new UserProfileFieldStore() )->register( $this->profile() );

		self::assertNotFalse( \has_action( 'show_user_profile' ) );
		self::assertNotFalse( \has_action( 'personal_options_update' ) );
	}

	public function test_the_own_profile_surface_is_omitted_when_restricted_to_admins(): void {
		$profile = new UserProfileFieldGroup( group: $this->group(), on_own_profile: false );
		( new UserProfileFieldStore() )->register( $profile );

		self::assertFalse( \has_action( 'show_user_profile' ) );
		self::assertFalse( \has_action( 'personal_options_update' ) );
		self::assertNotFalse( \has_action( 'edit_user_profile' ) );
		self::assertNotFalse( \has_action( 'edit_user_profile_update' ) );
	}

	public function test_saving_is_skipped_for_a_user_who_cannot_edit_the_target(): void {
		$other = \wp_insert_user(
			array( 'user_login' => 'dws_other_' . \uniqid(), 'user_pass' => 'x', 'role' => 'subscriber' ),
		);
		\assert( \is_int( $other ) );
		\wp_set_current_user( $other );

		( new UserProfileFieldStore() )->register( $this->profile() );

		$_POST = array( self::NONCE_NAME => $this->nonce(), self::GROUP_ID => array( 'pref' => '1' ) );
		\do_action( 'edit_user_profile_update', $this->user_id );

		self::assertFalse( $this->repo()->has( $this->user_id, 'pref' ) );

		\wp_delete_user( $other );
	}

	public function test_rendering_is_skipped_for_a_user_who_cannot_edit_the_target(): void {
		$other = \wp_insert_user(
			array( 'user_login' => 'dws_other_render_' . \uniqid(), 'user_pass' => 'x', 'role' => 'subscriber' ),
		);
		\assert( \is_int( $other ) );
		\wp_set_current_user( $other );

		( new UserProfileFieldStore() )->register( $this->profile() );

		\ob_start();
		\do_action( 'edit_user_profile', \get_userdata( $this->user_id ) );
		$html = (string) \ob_get_clean();

		self::assertStringNotContainsString( 'name="dws_prefs[pref]"', $html );

		\wp_delete_user( $other );
	}

	private function repo(): MetadataRepository {
		return new MetadataRepository( MetaType::User );
	}

	private function profile(): UserProfileFieldGroup {
		return new UserProfileFieldGroup( group: $this->group() );
	}

	protected function text_profile(): UserProfileFieldGroup {
		return $this->profile_with( new SettingsField( id: 'pref', type: 'text', label: 'Preference' ) );
	}

	private function profile_with( SettingsField $field ): UserProfileFieldGroup {
		return new UserProfileFieldGroup(
			group: new FieldGroup(
				id: self::GROUP_ID,
				title: 'Preferences',
				fields_provider: static fn ( int $object_id ): array => array( $field ),
			),
		);
	}

	private function group(): FieldGroup {
		return new FieldGroup(
			id: self::GROUP_ID,
			title: 'Preferences',
			fields_provider: static fn ( int $object_id ): array => array(
				new SettingsField( id: 'pref', type: 'checkbox', label: 'Preference' ),
			),
		);
	}

	private function nonce(): string {
		return \wp_create_nonce( self::NONCE_ACTION . '_' . $this->user_id );
	}
}
