<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Tests\Unit\MetaField;

use DeepWebSolutions\Framework\Settings\MetaField\ObjectFieldForm;
use DeepWebSolutions\Framework\Settings\MetaField\ObjectMetaRepositoryInterface;
use DeepWebSolutions\Framework\Settings\MetaField\PostMetaFieldStore;
use DeepWebSolutions\Framework\Settings\MetaField\ValueObjects\FieldGroup;
use DeepWebSolutions\Framework\Settings\Schema\Exceptions\DuplicateSettingsFieldException;
use DeepWebSolutions\Framework\Settings\Schema\Exceptions\InvalidSettingsFieldException;
use DeepWebSolutions\Framework\Settings\Schema\Field\FieldProcessor;
use DeepWebSolutions\Framework\Settings\Schema\Field\FieldRenderer;
use DeepWebSolutions\Framework\Settings\Schema\Options\OptionsResolver;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsField;
use DeepWebSolutions\Framework\Settings\Tests\Fixtures\InMemoryObjectMetaRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesFunction;
use PHPUnit\Framework\TestCase;

#[CoversClass( PostMetaFieldStore::class )]
#[UsesClass( ObjectFieldForm::class )]
#[UsesClass( FieldGroup::class )]
#[UsesClass( SettingsField::class )]
#[UsesClass( FieldRenderer::class )]
#[UsesClass( FieldProcessor::class )]
#[UsesClass( OptionsResolver::class )]
#[UsesFunction( 'DeepWebSolutions\Framework\Settings\Schema\is_valid_identifier' )]
#[UsesFunction( 'DeepWebSolutions\Framework\Settings\Schema\is_checkbox_checked' )]
#[UsesFunction( 'DeepWebSolutions\Framework\Settings\Schema\normalize_checkbox_value' )]
#[UsesFunction( 'DeepWebSolutions\Framework\Settings\Schema\wordpress_field_type_sanitizers' )]
final class PostMetaFieldStoreTest extends TestCase {
	private ObjectMetaRepositoryInterface $repository;
	private PostMetaFieldStore $store;

	protected function setUp(): void {
		parent::setUp();

		$this->repository = new InMemoryObjectMetaRepository();
		$this->store      = new PostMetaFieldStore( repository: $this->repository );
	}

	public function test_a_value_round_trips_under_the_field_id_when_no_meta_key_override_is_set(): void {
		$group = $this->group( new SettingsField( id: 'note', type: 'text', label: 'Note' ) );

		$this->store->set( $group, 7, 'note', 'hello' );

		self::assertSame( 'hello', $this->store->get( $group, 7, 'note' ) );
		self::assertSame( 'hello', $this->repository->get( 7, 'note' ) );
	}

	public function test_a_meta_key_override_is_the_byte_exact_storage_key(): void {
		$group = $this->group( new SettingsField( id: 'note', type: 'text', label: 'Note', meta_key: '_dws_note' ) );

		$this->store->set( $group, 7, 'note', 'hello' );

		self::assertSame( 'hello', $this->repository->get( 7, '_dws_note' ) );
		self::assertFalse( $this->repository->has( 7, 'note' ) );
	}

	public function test_get_returns_the_caller_fallback_never_the_field_default_when_nothing_is_stored(): void {
		$group = $this->group( new SettingsField( id: 'note', type: 'text', label: 'Note', default_value: 'declared-default' ) );

		self::assertNull( $this->store->get( $group, 7, 'note' ) );
		self::assertSame( 'fallback', $this->store->get( $group, 7, 'note', 'fallback' ) );
	}

	public function test_a_write_targets_only_the_addressed_object(): void {
		$group = $this->group( new SettingsField( id: 'note', type: 'text', label: 'Note' ) );

		$this->store->set( $group, 7, 'note', 'seven' );

		self::assertTrue( $this->store->has( $group, 7, 'note' ) );
		self::assertFalse( $this->store->has( $group, 8, 'note' ) );
	}

	public function test_set_stores_a_checkbox_in_its_canonical_yes_no_form(): void {
		$group = $this->group( new SettingsField( id: 'flag', type: 'checkbox', label: 'Flag' ) );

		$this->store->set( $group, 7, 'flag', true );
		self::assertSame( 'yes', $this->repository->get( 7, 'flag' ) );

		$this->store->set( $group, 7, 'flag', false );
		self::assertSame( 'no', $this->repository->get( 7, 'flag' ) );
		self::assertTrue( $this->store->has( $group, 7, 'flag' ) );
	}

	public function test_set_revokes_the_key_for_a_value_a_form_save_would_not_store(): void {
		$group = $this->group( new SettingsField( id: 'note', type: 'text', label: 'Note' ) );

		$this->store->set( $group, 7, 'note', 'hello' );
		$this->store->set( $group, 7, 'note', '' );

		self::assertFalse( $this->store->has( $group, 7, 'note' ) );
	}

	public function test_delete_removes_a_stored_value_and_reports_a_missing_one(): void {
		$group = $this->group( new SettingsField( id: 'note', type: 'text', label: 'Note' ) );

		$this->store->set( $group, 7, 'note', 'hello' );

		self::assertTrue( $this->store->delete( $group, 7, 'note' ) );
		self::assertFalse( $this->store->has( $group, 7, 'note' ) );
		self::assertFalse( $this->store->delete( $group, 7, 'note' ) );
	}

	public function test_a_field_the_group_does_not_declare_is_rejected(): void {
		$this->expectException( InvalidSettingsFieldException::class );

		$this->store->get( $this->group( new SettingsField( id: 'note', type: 'text', label: 'Note' ) ), 7, 'missing' );
	}

	public function test_two_fields_sharing_an_effective_storage_key_are_rejected(): void {
		$this->expectException( DuplicateSettingsFieldException::class );

		$this->store->get(
			$this->group(
				new SettingsField( id: 'note', type: 'text', label: 'A' ),
				new SettingsField( id: 'alias', type: 'text', label: 'B', meta_key: 'note' ),
			),
			7,
			'note',
		);
	}

	public function test_the_key_resolution_follows_a_per_object_fields_provider(): void {
		$group = new FieldGroup(
			id: 'dws_group',
			title: 'Group',
			fields_provider: static fn ( int $object_id ): array => array(
				new SettingsField( id: 'note', type: 'text', label: 'Note', meta_key: '_dws_note_' . $object_id ),
			),
		);

		$this->store->set( $group, 7, 'note', 'hello' );

		self::assertSame( 'hello', $this->repository->get( 7, '_dws_note_7' ) );
	}

	public function test_meta_keys_enumerates_the_resolved_storage_keys_for_the_objectless_evaluation(): void {
		$group = new FieldGroup(
			id: 'dws_group',
			title: 'Group',
			fields_provider: static fn ( int $object_id ): array => 0 === $object_id
				? array(
					new SettingsField( id: 'note', type: 'text', label: 'Note' ),
					new SettingsField( id: 'color', type: 'text', label: 'Color', meta_key: '_dws_color' ),
				)
				: array(),
		);

		self::assertSame( array( 'note', '_dws_color' ), $this->store->meta_keys( $group ) );
	}

	private function group( SettingsField ...$fields ): FieldGroup {
		$fields = \array_values( $fields );

		return new FieldGroup(
			id: 'dws_group',
			title: 'Group',
			fields_provider: static fn ( int $object_id ): array => $fields,
		);
	}
}
