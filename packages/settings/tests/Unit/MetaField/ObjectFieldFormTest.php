<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Tests\Unit\MetaField;

use DeepWebSolutions\Framework\Settings\MetaField\ObjectFieldForm;
use DeepWebSolutions\Framework\Settings\MetaField\ObjectMetaRepositoryInterface;
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

#[CoversClass( ObjectFieldForm::class )]
#[UsesClass( FieldGroup::class )]
#[UsesClass( SettingsField::class )]
#[UsesClass( FieldRenderer::class )]
#[UsesClass( FieldProcessor::class )]
#[UsesClass( OptionsResolver::class )]
#[UsesFunction( 'DeepWebSolutions\Framework\Settings\Schema\is_valid_identifier' )]
#[UsesFunction( 'DeepWebSolutions\Framework\Settings\Schema\is_checkbox_checked' )]
#[UsesFunction( 'DeepWebSolutions\Framework\Settings\Schema\normalize_checkbox_value' )]
#[UsesFunction( 'DeepWebSolutions\Framework\Settings\Schema\wordpress_field_type_sanitizers' )]
final class ObjectFieldFormTest extends TestCase {
	public function test_the_nonce_action_is_scoped_to_the_group_and_the_object(): void {
		$form  = $this->form();
		$group = $this->group( new SettingsField( id: 'note', type: 'text', label: 'Note' ) );

		self::assertSame( 'dws_object_field_dws_group_7', $form->get_nonce_action( $group, 7 ) );
		self::assertNotSame( $form->get_nonce_action( $group, 7 ), $form->get_nonce_action( $group, 8 ) );
	}

	public function test_the_nonce_name_is_scoped_to_the_group(): void {
		self::assertSame( 'dws_object_field_dws_group_nonce', $this->form()->get_nonce_name( $this->group( new SettingsField( id: 'note', type: 'text', label: 'Note' ) ) ) );
	}

	public function test_meta_key_of_resolves_the_field_id_when_no_override_is_set(): void {
		$group = $this->group( new SettingsField( id: 'note', type: 'text', label: 'Note' ) );

		self::assertSame( 'note', $this->form()->meta_key_of( $group, 7, 'note' ) );
	}

	public function test_meta_key_of_resolves_the_meta_key_override_byte_exactly(): void {
		$group = $this->group( new SettingsField( id: 'note', type: 'text', label: 'Note', meta_key: '_dws_note' ) );

		self::assertSame( '_dws_note', $this->form()->meta_key_of( $group, 7, 'note' ) );
	}

	public function test_meta_key_of_builds_the_fields_for_the_addressed_object(): void {
		// The provider varies the storage key per object, so the resolved key proves which object the
		// fields were built for.
		$group = new FieldGroup(
			id: 'dws_group',
			title: 'Group',
			fields_provider: static fn ( int $object_id ): array => array(
				new SettingsField( id: 'note', type: 'text', label: 'Note', meta_key: '_dws_note_' . $object_id ),
			),
		);

		self::assertSame( '_dws_note_7', $this->form()->meta_key_of( $group, 7, 'note' ) );
	}

	public function test_meta_key_of_rejects_a_field_the_group_does_not_declare(): void {
		$this->expectException( InvalidSettingsFieldException::class );

		$this->form()->meta_key_of( $this->group( new SettingsField( id: 'note', type: 'text', label: 'Note' ) ), 7, 'missing' );
	}

	public function test_meta_key_of_rejects_a_duplicate_field_id(): void {
		$this->expectException( DuplicateSettingsFieldException::class );

		// Distinct meta_key overrides keep the storage keys unique, so only the id check can reject.
		$this->form()->meta_key_of(
			$this->group(
				new SettingsField( id: 'note', type: 'text', label: 'A', meta_key: '_dws_a' ),
				new SettingsField( id: 'note', type: 'text', label: 'B', meta_key: '_dws_b' ),
			),
			7,
			'note',
		);
	}

	public function test_meta_key_of_rejects_two_fields_sharing_an_effective_storage_key(): void {
		$this->expectException( DuplicateSettingsFieldException::class );

		$this->form()->meta_key_of(
			$this->group(
				new SettingsField( id: 'note', type: 'text', label: 'A' ),
				new SettingsField( id: 'alias', type: 'text', label: 'B', meta_key: 'note' ),
			),
			7,
			'note',
		);
	}

	public function test_meta_keys_returns_every_resolved_storage_key_in_declaration_order(): void {
		$group = $this->group(
			new SettingsField( id: 'note', type: 'text', label: 'Note' ),
			new SettingsField( id: 'color', type: 'text', label: 'Color', meta_key: '_dws_color' ),
		);

		self::assertSame( array( 'note', '_dws_color' ), $this->form()->meta_keys( $group ) );
	}

	public function test_meta_keys_evaluates_the_provider_for_object_zero_by_default(): void {
		$group = new FieldGroup(
			id: 'dws_group',
			title: 'Group',
			fields_provider: static fn ( int $object_id ): array => 0 === $object_id
				? array( new SettingsField( id: 'note', type: 'text', label: 'Note' ) )
				: array(),
		);

		self::assertSame( array( 'note' ), $this->form()->meta_keys( $group ) );
	}

	public function test_meta_keys_evaluates_the_provider_for_an_explicit_object(): void {
		$group = new FieldGroup(
			id: 'dws_group',
			title: 'Group',
			fields_provider: static fn ( int $object_id ): array => array(
				new SettingsField( id: 'note', type: 'text', label: 'Note', meta_key: '_dws_note_' . $object_id ),
			),
		);

		self::assertSame( array( '_dws_note_9' ), $this->form()->meta_keys( $group, 9 ) );
	}

	public function test_store_writes_under_the_resolved_storage_key(): void {
		$repository = new InMemoryObjectMetaRepository();
		$form       = new ObjectFieldForm( $repository );
		$group      = $this->group( new SettingsField( id: 'note', type: 'text', label: 'Note', meta_key: '_dws_note' ) );

		$form->store( $group, 7, 'note', 'hello' );

		self::assertSame( 'hello', $repository->get( 7, '_dws_note' ) );
		self::assertFalse( $repository->has( 7, 'note' ) );
	}

	public function test_store_normalizes_a_checkbox_to_its_canonical_yes_no_form(): void {
		$repository = new InMemoryObjectMetaRepository();
		$form       = new ObjectFieldForm( $repository );
		$group      = $this->group( new SettingsField( id: 'flag', type: 'checkbox', label: 'Flag' ) );

		$form->store( $group, 7, 'flag', '1' );
		self::assertSame( 'yes', $repository->get( 7, 'flag' ) );

		$form->store( $group, 7, 'flag', false );
		self::assertSame( 'no', $repository->get( 7, 'flag' ) );
		self::assertTrue( $repository->has( 7, 'flag' ) );
	}

	public function test_store_revokes_the_key_for_each_value_a_form_save_would_not_store(): void {
		$repository = new InMemoryObjectMetaRepository();
		$form       = new ObjectFieldForm( $repository );
		$group      = $this->group( new SettingsField( id: 'note', type: 'text', label: 'Note' ) );

		foreach ( array( false, '', array() ) as $empty ) {
			$form->store( $group, 7, 'note', 'kept' );
			$form->store( $group, 7, 'note', $empty );

			self::assertFalse( $repository->has( 7, 'note' ) );
		}
	}

	public function test_store_preserves_a_meaningful_zero(): void {
		$repository = new InMemoryObjectMetaRepository();
		$form       = new ObjectFieldForm( $repository );
		$group      = $this->group( new SettingsField( id: 'note', type: 'text', label: 'Note' ) );

		$form->store( $group, 7, 'note', '0' );

		self::assertSame( '0', $repository->get( 7, 'note' ) );
	}

	public function test_store_rejects_a_field_the_group_does_not_declare(): void {
		$this->expectException( InvalidSettingsFieldException::class );

		( new ObjectFieldForm( new InMemoryObjectMetaRepository() ) )->store(
			$this->group( new SettingsField( id: 'note', type: 'text', label: 'Note' ) ),
			7,
			'missing',
			'x',
		);
	}

	private function form(): ObjectFieldForm {
		$repository = new class() implements ObjectMetaRepositoryInterface {
			public function get( int $object_id, string $meta_key, mixed $default_value = null ): mixed {
				return $default_value;
			}

			public function set( int $object_id, string $meta_key, mixed $value ): void {}

			public function has( int $object_id, string $meta_key ): bool {
				return false;
			}

			public function delete( int $object_id, string $meta_key ): bool {
				return false;
			}

			public function apply( int $object_id, array $sets, array $deletes ): void {}
		};

		return new ObjectFieldForm( $repository );
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
