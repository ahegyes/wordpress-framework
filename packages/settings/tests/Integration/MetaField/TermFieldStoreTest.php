<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Tests\Integration\MetaField;

use DeepWebSolutions\Framework\Settings\MetaField\MetaType;
use DeepWebSolutions\Framework\Settings\MetaField\MetadataRepository;
use DeepWebSolutions\Framework\Settings\MetaField\ObjectFieldForm;
use DeepWebSolutions\Framework\Settings\MetaField\TermFieldStore;
use DeepWebSolutions\Framework\Settings\MetaField\ValueObjects\FieldGroup;
use DeepWebSolutions\Framework\Settings\MetaField\ValueObjects\TermFieldGroup;
use DeepWebSolutions\Framework\Settings\Schema\Field\FieldProcessor;
use DeepWebSolutions\Framework\Settings\Schema\Field\FieldRenderer;
use DeepWebSolutions\Framework\Settings\Schema\Field\FieldType;
use DeepWebSolutions\Framework\Settings\Schema\Options\OptionsResolver;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsField;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( TermFieldStore::class )]
#[UsesClass( ObjectFieldForm::class )]
#[UsesClass( MetadataRepository::class )]
#[UsesClass( MetaType::class )]
#[UsesClass( FieldGroup::class )]
#[UsesClass( TermFieldGroup::class )]
#[UsesClass( SettingsField::class )]
#[UsesClass( FieldRenderer::class )]
#[UsesClass( FieldProcessor::class )]
#[UsesClass( OptionsResolver::class )]
#[UsesClass( FieldType::class )]
final class TermFieldStoreTest extends TestCase {
	private const GROUP_ID     = 'dws_termmeta';
	private const NONCE_NAME   = 'dws_object_field_dws_termmeta_nonce';
	private const NONCE_ACTION = 'dws_object_field_dws_termmeta';
	private const ISOLATED_HOOKS = array( 'category_add_form_fields', 'category_edit_form_fields', 'created_category', 'edited_category' );

	private int $term_id = 0;

	/**
	 * @var array<string, mixed>
	 */
	private array $saved_hooks = array();

	protected function setUp(): void {
		parent::setUp();

		\wp_set_current_user( 1 );
		$_POST = array();

		global $wp_filter;
		foreach ( self::ISOLATED_HOOKS as $hook ) {
			$this->saved_hooks[ $hook ] = $wp_filter[ $hook ] ?? null;
			unset( $wp_filter[ $hook ] );
		}

		$term = \wp_insert_term( 'DWS Probe ' . \uniqid(), 'category' );
		\assert( \is_array( $term ) );
		$this->term_id = (int) $term['term_id'];
	}

	protected function tearDown(): void {
		\wp_delete_term( $this->term_id, 'category' );
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

	public function test_editing_a_term_renders_the_nonce_and_control(): void {
		( new TermFieldStore() )->register( $this->term_group() );

		\ob_start();
		\do_action( 'category_edit_form_fields', \get_term( $this->term_id, 'category' ) );
		$html = (string) \ob_get_clean();

		self::assertStringContainsString( self::NONCE_NAME, $html );
		self::assertStringContainsString( 'name="dws_termmeta[color]"', $html );
		self::assertStringContainsString( '<tr', $html );
	}

	public function test_adding_a_term_renders_the_nonce_and_control_in_add_form_markup(): void {
		( new TermFieldStore() )->register( $this->term_group() );

		\ob_start();
		\do_action( 'category_add_form_fields', 'category' );
		$html = (string) \ob_get_clean();

		self::assertStringContainsString( self::NONCE_NAME, $html );
		self::assertStringContainsString( 'name="dws_termmeta[color]"', $html );
		self::assertStringContainsString( '<div class="form-field term-color-wrap">', $html );
		self::assertStringNotContainsString( '<tr', $html );
	}

	public function test_the_hooks_are_registered_for_the_descriptor_taxonomy(): void {
		( new TermFieldStore() )->register( $this->term_group() );

		self::assertNotFalse( \has_action( 'category_edit_form_fields' ) );
		self::assertNotFalse( \has_action( 'edited_category' ) );
		self::assertNotFalse( \has_action( 'category_add_form_fields' ) );
		self::assertNotFalse( \has_action( 'created_category' ) );
	}

	public function test_saving_persists_with_capability_and_a_valid_nonce(): void {
		( new TermFieldStore() )->register( $this->term_group() );

		$_POST = array( self::NONCE_NAME => $this->nonce(), self::GROUP_ID => array( 'color' => 'blue' ) );
		\do_action( 'edited_category', $this->term_id );

		self::assertSame( 'blue', $this->repo()->get( $this->term_id, 'color' ) );
	}

	public function test_saving_a_created_term_persists_with_capability_and_a_valid_add_nonce(): void {
		( new TermFieldStore() )->register( $this->term_group() );

		$_POST = array( self::NONCE_NAME => $this->nonce_for( 0 ), self::GROUP_ID => array( 'color' => 'blue' ) );
		\do_action( 'created_category', $this->term_id );

		self::assertSame( 'blue', $this->repo()->get( $this->term_id, 'color' ) );
	}

	public function test_saving_applies_the_builtin_default_sanitizer(): void {
		$raw = '<b>x</b>';
		( new TermFieldStore() )->register( $this->term_group() );

		$_POST = array( self::NONCE_NAME => $this->nonce(), self::GROUP_ID => array( 'color' => $raw ) );
		\do_action( 'edited_category', $this->term_id );

		self::assertSame( \sanitize_text_field( $raw ), $this->repo()->get( $this->term_id, 'color' ) );
	}

	public function test_saving_preserves_an_existing_value_when_a_present_submission_is_invalid(): void {
		( new TermFieldStore() )->register(
			$this->term_group_with(
				new SettingsField( id: 'color', type: 'select', label: 'Color', options: array( 'red' => 'Red' ) ),
			),
		);

		$this->repo()->set( $this->term_id, 'color', 'red' );
		$_POST = array( self::NONCE_NAME => $this->nonce(), self::GROUP_ID => array( 'color' => 'blue' ) );
		\do_action( 'edited_category', $this->term_id );

		self::assertSame( 'red', $this->repo()->get( $this->term_id, 'color' ) );
	}

	public function test_saving_is_skipped_without_a_valid_nonce(): void {
		( new TermFieldStore() )->register( $this->term_group() );

		$_POST = array( self::GROUP_ID => array( 'color' => 'blue' ) );
		\do_action( 'edited_category', $this->term_id );

		self::assertFalse( $this->repo()->has( $this->term_id, 'color' ) );
	}

	public function test_saving_is_skipped_for_a_user_without_the_term_capability(): void {
		$subscriber = \wp_insert_user(
			array( 'user_login' => 'dws_sub_' . \uniqid(), 'user_pass' => 'x', 'role' => 'subscriber' ),
		);
		\assert( \is_int( $subscriber ) );
		\wp_set_current_user( $subscriber );

		( new TermFieldStore() )->register( $this->term_group() );

		$_POST = array( self::NONCE_NAME => $this->nonce(), self::GROUP_ID => array( 'color' => 'blue' ) );
		\do_action( 'edited_category', $this->term_id );

		self::assertFalse( $this->repo()->has( $this->term_id, 'color' ) );

		require_once ABSPATH . 'wp-admin/includes/user.php';
		\wp_delete_user( $subscriber );
	}

	public function test_the_add_form_gates_on_edit_terms_not_manage_terms(): void {
		\register_taxonomy(
			'dws_split_cap_tax',
			'post',
			array(
				'capabilities' => array(
					'manage_terms' => 'manage_options',
					'edit_terms'   => 'edit_posts',
					'delete_terms' => 'manage_options',
					'assign_terms' => 'edit_posts',
				),
			),
		);

		$author = \wp_insert_user(
			array( 'user_login' => 'dws_author_' . \uniqid(), 'user_pass' => 'x', 'role' => 'author' ),
		);
		\assert( \is_int( $author ) );
		\wp_set_current_user( $author );

		try {
			$field = new SettingsField( id: 'color', type: 'text', label: 'Color' );
			$group = new FieldGroup(
				id: self::GROUP_ID,
				title: 'Split Cap Meta',
				fields_provider: static fn ( int $object_id ): array => array( $field ),
			);
			( new TermFieldStore() )->register( new TermFieldGroup( group: $group, taxonomy: 'dws_split_cap_tax' ) );

			\ob_start();
			\do_action( 'dws_split_cap_tax_add_form_fields', 'dws_split_cap_tax' );
			$html = (string) \ob_get_clean();

			// WordPress gates the add-term form itself on cap->edit_terms; an author holds
			// edit_posts (this taxonomy's edit_terms) but not manage_options (its manage_terms),
			// so the fields must render under the same gate.
			self::assertStringContainsString( 'name="dws_termmeta[color]"', $html );
		} finally {
			foreach ( array( 'dws_split_cap_tax_add_form_fields', 'dws_split_cap_tax_edit_form_fields', 'created_dws_split_cap_tax', 'edited_dws_split_cap_tax' ) as $hook ) {
				\remove_all_filters( $hook );
			}
			\unregister_taxonomy( 'dws_split_cap_tax' );

			require_once ABSPATH . 'wp-admin/includes/user.php';
			\wp_delete_user( $author );
		}
	}

	public function test_rendering_is_skipped_for_a_user_without_the_term_capability(): void {
		$subscriber = \wp_insert_user(
			array( 'user_login' => 'dws_sub_render_' . \uniqid(), 'user_pass' => 'x', 'role' => 'subscriber' ),
		);
		\assert( \is_int( $subscriber ) );
		\wp_set_current_user( $subscriber );

		( new TermFieldStore() )->register( $this->term_group() );

		\ob_start();
		\do_action( 'category_edit_form_fields', \get_term( $this->term_id, 'category' ) );
		$html = (string) \ob_get_clean();

		self::assertStringNotContainsString( 'name="dws_termmeta[color]"', $html );

		require_once ABSPATH . 'wp-admin/includes/user.php';
		\wp_delete_user( $subscriber );
	}

	private function repo(): MetadataRepository {
		return new MetadataRepository( MetaType::Term );
	}

	private function term_group(): TermFieldGroup {
		return $this->term_group_with( new SettingsField( id: 'color', type: 'text', label: 'Color' ) );
	}

	private function term_group_with( SettingsField $field ): TermFieldGroup {
		$group = new FieldGroup(
			id: self::GROUP_ID,
			title: 'Category Meta',
			fields_provider: static fn ( int $object_id ): array => array( $field ),
		);

		return new TermFieldGroup( group: $group, taxonomy: 'category' );
	}

	private function nonce(): string {
		return $this->nonce_for( $this->term_id );
	}

	private function nonce_for( int $object_id ): string {
		return \wp_create_nonce( self::NONCE_ACTION . '_' . $object_id );
	}
}
