<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Tests\Integration\MetaField;

use DeepWebSolutions\Framework\Settings\MetaField\MetaType;
use DeepWebSolutions\Framework\Settings\MetaField\MetadataRepository;
use DeepWebSolutions\Framework\Settings\MetaField\ObjectFieldForm;
use DeepWebSolutions\Framework\Settings\MetaField\PostMetaFieldStore;
use DeepWebSolutions\Framework\Settings\MetaField\ValueObjects\FieldGroup;
use DeepWebSolutions\Framework\Settings\MetaField\ValueObjects\MetaBoxPlacement;
use DeepWebSolutions\Framework\Settings\Schema\Field\FieldProcessor;
use DeepWebSolutions\Framework\Settings\Schema\Field\FieldRenderer;
use DeepWebSolutions\Framework\Settings\Schema\Field\FieldType;
use DeepWebSolutions\Framework\Settings\Schema\Options\OptionsResolver;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsField;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( PostMetaFieldStore::class )]
#[UsesClass( ObjectFieldForm::class )]
#[UsesClass( MetadataRepository::class )]
#[UsesClass( MetaType::class )]
#[UsesClass( FieldGroup::class )]
#[UsesClass( MetaBoxPlacement::class )]
#[UsesClass( SettingsField::class )]
#[UsesClass( FieldRenderer::class )]
#[UsesClass( FieldProcessor::class )]
#[UsesClass( OptionsResolver::class )]
#[UsesClass( FieldType::class )]
final class PostMetaFieldStoreTest extends TestCase {
	private const GROUP_ID     = 'dws_postmeta';
	private const ISOLATED_HOOKS = array( 'add_meta_boxes_post', 'save_post_post' );

	private int $post_id = 0;

	/**
	 * @var array<string, mixed>
	 */
	private array $saved_hooks = array();

	protected function setUp(): void {
		parent::setUp();

		require_once ABSPATH . 'wp-admin/includes/template.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		require_once ABSPATH . 'wp-admin/includes/screen.php';

		\wp_set_current_user( 1 );
		$_POST                    = array();
		$GLOBALS['wp_meta_boxes'] = array();

		global $wp_filter;
		foreach ( self::ISOLATED_HOOKS as $hook ) {
			$this->saved_hooks[ $hook ] = $wp_filter[ $hook ] ?? null;
			unset( $wp_filter[ $hook ] );
		}

		$post_id = \wp_insert_post( array( 'post_title' => 'Probe', 'post_status' => 'publish' ) );
		\assert( \is_int( $post_id ) );
		$this->post_id = $post_id;
	}

	protected function tearDown(): void {
		\wp_delete_post( $this->post_id, true );
		$_POST                    = array();
		$GLOBALS['wp_meta_boxes'] = array();

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

	public function test_register_adds_the_meta_box_on_the_screen(): void {
		( new PostMetaFieldStore() )->register( $this->group(), $this->placement() );
		\do_action( 'add_meta_boxes_post', \get_post( $this->post_id ) );

		self::assertArrayHasKey( self::GROUP_ID, $this->boxes_on( 'post' ) );
	}

	public function test_the_registered_box_renders_a_nonce_and_its_control(): void {
		$group = $this->group();
		( new PostMetaFieldStore() )->register( $group, $this->placement() );
		\do_action( 'add_meta_boxes_post', \get_post( $this->post_id ) );

		$html = $this->render_box();

		self::assertStringContainsString( $this->nonce_name( $group ), $html );
		self::assertStringContainsString( 'name="dws_postmeta[note]"', $html );
	}

	public function test_saving_persists_with_capability_and_a_valid_nonce(): void {
		$store = new PostMetaFieldStore();
		$group = $this->group();
		$store->register( $group, $this->placement() );

		$_POST = array( $this->nonce_name( $group ) => $this->nonce( $group ), self::GROUP_ID => array( 'note' => 'hi' ) );
		\do_action( 'save_post_post', $this->post_id );

		self::assertSame( 'hi', $this->repo()->get( $this->post_id, 'note' ) );
	}

	public function test_saving_applies_the_builtin_default_sanitizer(): void {
		$store = new PostMetaFieldStore();
		$group = $this->group();
		$raw   = '<b>x</b>';
		$store->register( $group, $this->placement() );

		$_POST = array( $this->nonce_name( $group ) => $this->nonce( $group ), self::GROUP_ID => array( 'note' => $raw ) );
		\do_action( 'save_post_post', $this->post_id );

		self::assertSame( \sanitize_text_field( $raw ), $this->repo()->get( $this->post_id, 'note' ) );
	}

	public function test_saving_preserves_an_existing_value_when_a_present_submission_is_invalid(): void {
		$store = new PostMetaFieldStore();
		$group = $this->group_with(
			new SettingsField( id: 'color', type: 'select', label: 'Color', options: array( 'red' => 'Red' ) ),
		);
		$store->register( $group, $this->placement() );

		$this->repo()->set( $this->post_id, 'color', 'red' );
		$_POST = array( $this->nonce_name( $group ) => $this->nonce( $group ), self::GROUP_ID => array( 'color' => 'blue' ) );
		\do_action( 'save_post_post', $this->post_id );

		self::assertSame( 'red', $this->repo()->get( $this->post_id, 'color' ) );
	}

	public function test_saving_is_skipped_without_a_valid_nonce(): void {
		( new PostMetaFieldStore() )->register( $this->group(), $this->placement() );

		$_POST = array( self::GROUP_ID => array( 'note' => 'hi' ) );
		\do_action( 'save_post_post', $this->post_id );

		self::assertFalse( $this->repo()->has( $this->post_id, 'note' ) );
	}

	public function test_saving_is_skipped_for_a_user_without_the_edit_capability(): void {
		$subscriber = \wp_insert_user(
			array( 'user_login' => 'dws_sub_' . \uniqid(), 'user_pass' => 'x', 'role' => 'subscriber' ),
		);
		\assert( \is_int( $subscriber ) );
		\wp_set_current_user( $subscriber );

		$group = $this->group();
		( new PostMetaFieldStore() )->register( $group, $this->placement() );

		$_POST = array( $this->nonce_name( $group ) => $this->nonce( $group ), self::GROUP_ID => array( 'note' => 'hi' ) );
		\do_action( 'save_post_post', $this->post_id );

		self::assertFalse( $this->repo()->has( $this->post_id, 'note' ) );

		require_once ABSPATH . 'wp-admin/includes/user.php';
		\wp_delete_user( $subscriber );
	}

	public function test_a_configured_box_capability_overrides_the_default(): void {
		$store     = new PostMetaFieldStore();
		$placement = new MetaBoxPlacement( screen: 'post', context: 'side', priority: 'default', capability: 'dws_nonexistent_cap' );
		$group     = $this->group();
		$store->register( $group, $placement );

		// The administrator passes the default edit_post but lacks the configured capability, so the save is refused.
		$_POST = array( $this->nonce_name( $group ) => $this->nonce( $group ), self::GROUP_ID => array( 'note' => 'hi' ) );
		\do_action( 'save_post_post', $this->post_id );

		self::assertFalse( $this->repo()->has( $this->post_id, 'note' ) );
	}

	public function test_a_configured_capability_hides_the_box_on_render(): void {
		$placement = new MetaBoxPlacement( screen: 'post', context: 'side', priority: 'default', capability: 'dws_nonexistent_cap' );
		( new PostMetaFieldStore() )->register( $this->group(), $placement );
		\do_action( 'add_meta_boxes_post', \get_post( $this->post_id ) );

		// The administrator reaches the edit screen but lacks the configured capability, so the box is not added.
		self::assertArrayNotHasKey( self::GROUP_ID, $this->boxes_on( 'post' ) );
	}

	private function render_box(): string {
		$definition = (array) ( $this->boxes_on( 'post' )[ self::GROUP_ID ] ?? array() );
		$callback   = $definition['callback'] ?? null;
		\assert( \is_callable( $callback ) );

		\ob_start();
		$callback( \get_post( $this->post_id ) );

		return (string) \ob_get_clean();
	}

	/**
	 * @return array<string, mixed>
	 */
	private function boxes_on( string $screen ): array {
		global $wp_meta_boxes;
		$by_priority = (array) ( ( (array) ( ( (array) $wp_meta_boxes )[ $screen ] ?? array() ) )['side'] ?? array() );

		return (array) ( $by_priority['default'] ?? array() );
	}

	private function repo(): MetadataRepository {
		return new MetadataRepository( MetaType::Post );
	}

	private function group(): FieldGroup {
		return $this->group_with( new SettingsField( id: 'note', type: 'text', label: 'Note' ) );
	}

	private function group_with( SettingsField $field ): FieldGroup {
		return new FieldGroup(
			id: self::GROUP_ID,
			title: 'Post Meta',
			fields_provider: static fn ( int $object_id ): array => array( $field ),
		);
	}

	private function placement(): MetaBoxPlacement {
		return new MetaBoxPlacement( screen: 'post', context: 'side', priority: 'default' );
	}

	protected function nonce_name( FieldGroup $group ): string {
		return ( new ObjectFieldForm( $this->repo() ) )->get_nonce_name( $group );
	}

	protected function nonce( FieldGroup $group ): string {
		return \wp_create_nonce( ( new ObjectFieldForm( $this->repo() ) )->get_nonce_action( $group, $this->post_id ) );
	}
}
