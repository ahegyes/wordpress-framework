<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Tests\Integration\MetaField;

use DeepWebSolutions\Framework\Settings\MetaField\MetadataRepository;
use DeepWebSolutions\Framework\Settings\MetaField\MetaType;
use DeepWebSolutions\Framework\Settings\MetaField\ObjectFieldForm;
use DeepWebSolutions\Framework\Settings\MetaField\ValueObjects\FieldGroup;
use DeepWebSolutions\Framework\Settings\Schema\Exceptions\DuplicateSettingsFieldException;
use DeepWebSolutions\Framework\Settings\Schema\Field\FieldProcessor;
use DeepWebSolutions\Framework\Settings\Schema\Field\FieldRenderer;
use DeepWebSolutions\Framework\Settings\Schema\Field\FieldType;
use DeepWebSolutions\Framework\Settings\Schema\Options\OptionsResolver;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\CustomFieldType;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsField;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( ObjectFieldForm::class )]
#[UsesClass( MetadataRepository::class )]
#[UsesClass( MetaType::class )]
#[UsesClass( FieldGroup::class )]
#[UsesClass( SettingsField::class )]
#[UsesClass( FieldRenderer::class )]
#[UsesClass( FieldProcessor::class )]
#[UsesClass( OptionsResolver::class )]
#[UsesClass( FieldType::class )]
#[UsesClass( CustomFieldType::class )]
final class ObjectFieldFormTest extends TestCase {
	private const GROUP_ID    = 'dws_box';
	private const NONCE_NAME  = 'dws_object_field_dws_box_nonce';
	private const NONCE_ACTION = 'dws_object_field_dws_box';

	private int $post_id = 0;

	protected function setUp(): void {
		parent::setUp();

		\wp_set_current_user( 1 );
		$_POST = array();

		$post_id = \wp_insert_post( array( 'post_title' => 'Probe', 'post_status' => 'publish' ) );
		\assert( \is_int( $post_id ) );
		$this->post_id = $post_id;
	}

	protected function tearDown(): void {
		\wp_delete_post( $this->post_id, true );
		$_POST = array();

		parent::tearDown();
	}

	public function test_render_emits_the_nonce_and_a_namespaced_control(): void {
		\ob_start();
		$this->form()->render( $this->group( $this->text_field() ), $this->post_id );
		$html = (string) \ob_get_clean();

		self::assertStringContainsString( self::NONCE_NAME, $html );
		self::assertStringContainsString( 'name="dws_box[note]"', $html );
	}

	public function test_render_wraps_each_field_with_the_row_closure(): void {
		$row = static fn ( SettingsField $field, string $control ): string =>
			'<tr><th>' . \esc_html( $field->label ) . '</th><td>' . $control . '</td></tr>';

		\ob_start();
		$this->form()->render( $this->group( $this->text_field() ), $this->post_id, $row );
		$html = (string) \ob_get_clean();

		self::assertStringContainsString( '<tr><th>Note</th><td>', $html );
		self::assertStringContainsString( 'name="dws_box[note]"', $html );
	}

	public function test_render_uses_a_bespoke_renderer_and_still_emits_the_nonce(): void {
		$group = new FieldGroup(
			id: self::GROUP_ID,
			title: 'Bespoke',
			fields_provider: static fn ( int $object_id ): array => array(),
			render: static fn ( int $object_id ): string => '<p>bespoke</p>',
		);

		\ob_start();
		$this->form()->render( $group, $this->post_id );
		$html = (string) \ob_get_clean();

		self::assertStringContainsString( self::NONCE_NAME, $html );
		self::assertStringContainsString( '<p>bespoke</p>', $html );
	}

	public function test_render_skips_a_field_the_current_user_cannot_edit(): void {
		$field = new SettingsField( id: 'secret', type: 'text', label: 'Secret', capability: 'dws_nonexistent_cap' );

		\ob_start();
		$this->form()->render( $this->group( $field ), $this->post_id );
		$html = (string) \ob_get_clean();

		self::assertStringNotContainsString( 'name="dws_box[secret]"', $html );
	}

	public function test_render_reads_revoke_based_so_a_default_does_not_spring_back(): void {
		$field = new SettingsField( id: 'note', type: 'text', label: 'Note', default_value: 'preset' );

		\ob_start();
		$this->form()->render( $this->group( $field ), $this->post_id );
		$html = (string) \ob_get_clean();

		// Nothing is stored, and object fields render unset rather than their default.
		self::assertStringNotContainsString( 'preset', $html );
	}

	public function test_save_stores_a_truthy_submission_and_revokes_an_absent_one(): void {
		$form  = $this->form();
		$group = $this->group( new SettingsField( id: 'unlocked', type: 'checkbox', label: 'Unlocked' ) );

		$_POST = array( self::NONCE_NAME => $this->nonce(), self::GROUP_ID => array( 'unlocked' => '1' ) );
		$form->save( $group, $this->post_id );
		self::assertTrue( $this->repo()->has( $this->post_id, 'unlocked' ) );
		self::assertSame( 'yes', $this->repo()->get( $this->post_id, 'unlocked' ) );

		$_POST = array( self::NONCE_NAME => $this->nonce() );
		$form->save( $group, $this->post_id );
		self::assertFalse( $this->repo()->has( $this->post_id, 'unlocked' ) );
	}

	public function test_save_normalizes_a_checkbox_submission_to_yes_no(): void {
		$form  = $this->form();
		$group = $this->group( new SettingsField( id: 'unlocked', type: 'checkbox', label: 'Unlocked' ) );

		$_POST = array( self::NONCE_NAME => $this->nonce(), self::GROUP_ID => array( 'unlocked' => 'off' ) );
		$form->save( $group, $this->post_id );

		self::assertSame( 'no', $this->repo()->get( $this->post_id, 'unlocked' ) );
	}

	public function test_save_is_skipped_without_a_valid_nonce(): void {
		$group = $this->group( new SettingsField( id: 'unlocked', type: 'checkbox', label: 'Unlocked' ) );

		$_POST = array( self::GROUP_ID => array( 'unlocked' => '1' ) );
		$this->form()->save( $group, $this->post_id );

		self::assertFalse( $this->repo()->has( $this->post_id, 'unlocked' ) );
	}

	public function test_save_runs_a_bespoke_save_after_the_nonce(): void {
		$saved_for = 0;
		$group     = new FieldGroup(
			id: self::GROUP_ID,
			title: 'Bespoke',
			fields_provider: static fn ( int $object_id ): array => array(),
			save: function ( int $object_id ) use ( &$saved_for ): void {
				$saved_for = $object_id;
			},
		);

		$_POST = array( self::NONCE_NAME => $this->nonce() );
		$this->form()->save( $group, $this->post_id );

		self::assertSame( $this->post_id, $saved_for );
	}

	public function test_save_rejects_a_duplicate_field_id(): void {
		$group = new FieldGroup(
			id: self::GROUP_ID,
			title: 'Dup',
			fields_provider: static fn ( int $object_id ): array => array(
				new SettingsField( id: 'flag', type: 'checkbox', label: 'A' ),
				new SettingsField( id: 'flag', type: 'checkbox', label: 'B' ),
			),
		);

		$_POST = array( self::NONCE_NAME => $this->nonce(), self::GROUP_ID => array( 'flag' => '1' ) );

		$this->expectException( DuplicateSettingsFieldException::class );
		$this->form()->save( $group, $this->post_id );
	}

	public function test_save_stores_a_zero_value_rather_than_revoking_it(): void {
		$form  = $this->form();
		$group = $this->group( new SettingsField( id: 'note', type: 'text', label: 'Note' ) );

		$_POST = array( self::NONCE_NAME => $this->nonce(), self::GROUP_ID => array( 'note' => '0' ) );
		$form->save( $group, $this->post_id );

		self::assertTrue( $this->repo()->has( $this->post_id, 'note' ) );
		self::assertSame( '0', $this->repo()->get( $this->post_id, 'note' ) );
	}

	public function test_save_applies_the_builtin_default_sanitizer(): void {
		$form  = new ObjectFieldForm( $this->repo() );
		$group = $this->group( new SettingsField( id: 'note', type: 'text', label: 'Note' ) );
		$raw   = '<b>x</b>';

		$_POST = array( self::NONCE_NAME => $this->nonce(), self::GROUP_ID => array( 'note' => $raw ) );
		$form->save( $group, $this->post_id );

		self::assertSame( \sanitize_text_field( $raw ), $this->repo()->get( $this->post_id, 'note' ) );
	}

	public function test_save_preserves_an_existing_value_when_a_present_submission_is_invalid(): void {
		$form  = $this->form();
		$group = $this->group(
			new SettingsField( id: 'color', type: 'select', label: 'Color', options: array( 'red' => 'Red' ) ),
		);

		$this->repo()->set( $this->post_id, 'color', 'red' );
		$_POST = array( self::NONCE_NAME => $this->nonce(), self::GROUP_ID => array( 'color' => 'blue' ) );
		$form->save( $group, $this->post_id );

		self::assertSame( 'red', $this->repo()->get( $this->post_id, 'color' ) );
	}

	public function test_save_applies_multiple_fields(): void {
		$form  = $this->form();
		$group = new FieldGroup(
			id: self::GROUP_ID,
			title: 'Multi',
			fields_provider: static fn ( int $object_id ): array => array(
				new SettingsField( id: 'first', type: 'text', label: 'First' ),
				new SettingsField( id: 'second', type: 'text', label: 'Second' ),
			),
		);

		$_POST = array( self::NONCE_NAME => $this->nonce(), self::GROUP_ID => array( 'first' => 'A', 'second' => 'B' ) );
		$form->save( $group, $this->post_id );

		self::assertSame( 'A', $this->repo()->get( $this->post_id, 'first' ) );
		self::assertSame( 'B', $this->repo()->get( $this->post_id, 'second' ) );
	}

	public function test_save_skips_a_field_the_current_user_cannot_edit(): void {
		$form  = $this->form();
		$group = new FieldGroup(
			id: self::GROUP_ID,
			title: 'Mixed',
			fields_provider: static fn ( int $object_id ): array => array(
				new SettingsField( id: 'open', type: 'text', label: 'Open' ),
				new SettingsField( id: 'secret', type: 'text', label: 'Secret', capability: 'dws_nonexistent_cap' ),
			),
		);

		$_POST = array(
			self::NONCE_NAME => $this->nonce(),
			self::GROUP_ID   => array( 'open' => 'visible', 'secret' => 'tampered' ),
		);
		$form->save( $group, $this->post_id );

		self::assertSame( 'visible', $this->repo()->get( $this->post_id, 'open' ) );
		self::assertFalse( $this->repo()->has( $this->post_id, 'secret' ) );
	}

	public function test_a_field_meta_key_overrides_the_id_for_storage(): void {
		$form  = $this->form();
		$group = $this->group(
			new SettingsField( id: 'unlocked', type: 'checkbox', label: 'Unlocked', meta_key: '_lpm_unlocked' ),
		);

		$_POST = array( self::NONCE_NAME => $this->nonce(), self::GROUP_ID => array( 'unlocked' => '1' ) );
		$form->save( $group, $this->post_id );

		self::assertTrue( $this->repo()->has( $this->post_id, '_lpm_unlocked' ) );
		self::assertFalse( $this->repo()->has( $this->post_id, 'unlocked' ) );
	}

	public function test_save_rejects_two_fields_sharing_a_storage_key(): void {
		$group = new FieldGroup(
			id: self::GROUP_ID,
			title: 'Shared',
			fields_provider: static fn ( int $object_id ): array => array(
				new SettingsField( id: 'a', type: 'text', label: 'A', meta_key: '_shared' ),
				new SettingsField( id: 'b', type: 'text', label: 'B', meta_key: '_shared' ),
			),
		);

		$_POST = array( self::NONCE_NAME => $this->nonce(), self::GROUP_ID => array( 'a' => 'x', 'b' => '' ) );

		$this->expectException( DuplicateSettingsFieldException::class );
		$this->form()->save( $group, $this->post_id );
	}

	public function test_save_revokes_an_absent_custom_field_rather_than_storing_its_default(): void {
		$custom_types = array(
			'page_picker' => new CustomFieldType(
				type: 'page_picker',
				render: static fn ( SettingsField $field, mixed $value, string $name ): string =>
					'<input name="' . \esc_attr( $name ) . '" value="' . \esc_attr( (string) $value ) . '" />',
			),
		);
		$form  = new ObjectFieldForm(
			$this->repo(),
			new FieldRenderer( custom_types: $custom_types ),
			new FieldProcessor( custom_types: $custom_types ),
		);
		$group = $this->group(
			new SettingsField( id: 'picker', type: 'page_picker', label: 'Picker', default_value: 'DEFAULT' ),
		);

		// A value is stored, then the field is submitted absent: a custom type must revoke it, not resurrect
		// its declared default.
		$this->repo()->set( $this->post_id, 'picker', 'previously' );
		$_POST = array( self::NONCE_NAME => $this->nonce() );
		$form->save( $group, $this->post_id );

		self::assertFalse( $this->repo()->has( $this->post_id, 'picker' ) );
	}

	private function repo(): MetadataRepository {
		return new MetadataRepository( MetaType::Post );
	}

	private function form(): ObjectFieldForm {
		return new ObjectFieldForm( $this->repo(), new FieldRenderer(), new FieldProcessor() );
	}

	private function group( SettingsField $field ): FieldGroup {
		return new FieldGroup(
			id: self::GROUP_ID,
			title: 'Box',
			fields_provider: static fn ( int $object_id ): array => array( $field ),
		);
	}

	private function text_field(): SettingsField {
		return new SettingsField( id: 'note', type: 'text', label: 'Note' );
	}

	private function nonce(): string {
		return \wp_create_nonce( self::NONCE_ACTION . '_' . $this->post_id );
	}
}
