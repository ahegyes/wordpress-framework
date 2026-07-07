<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Tests\Unit\MetaField\Surfaces;

use DeepWebSolutions\Framework\Settings\MetaField\ObjectFieldForm;
use DeepWebSolutions\Framework\Settings\MetaField\Surfaces\UserProfileFieldSurface;
use DeepWebSolutions\Framework\Settings\MetaField\ValueObjects\FieldGroup;
use DeepWebSolutions\Framework\Settings\Schema\Exceptions\InvalidSettingsFieldException;
use DeepWebSolutions\Framework\Settings\Schema\Field\FieldProcessor;
use DeepWebSolutions\Framework\Settings\Schema\Field\FieldRenderer;
use DeepWebSolutions\Framework\Settings\Schema\Options\OptionsResolver;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsField;
use DeepWebSolutions\Framework\Settings\Tests\Fixtures\InMemoryObjectMetaRepository;
use DeepWebSolutions\Framework\Storage\ObjectMeta\ObjectMetaRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesFunction;
use PHPUnit\Framework\TestCase;

#[CoversClass( UserProfileFieldSurface::class )]
#[UsesClass( ObjectFieldForm::class )]
#[UsesClass( FieldGroup::class )]
#[UsesClass( SettingsField::class )]
#[UsesClass( FieldRenderer::class )]
#[UsesClass( FieldProcessor::class )]
#[UsesClass( OptionsResolver::class )]
#[UsesFunction( 'DeepWebSolutions\Framework\Shared\Identifier\is_valid_identifier' )]
#[UsesFunction( 'DeepWebSolutions\Framework\Settings\Schema\is_checkbox_checked' )]
#[UsesFunction( 'DeepWebSolutions\Framework\Settings\Schema\normalize_checkbox_value' )]
#[UsesFunction( 'DeepWebSolutions\Framework\Settings\Schema\wordpress_field_type_sanitizers' )]
final class UserProfileFieldSurfaceTest extends TestCase {
	private ObjectMetaRepositoryInterface $repository;
	private UserProfileFieldSurface $store;

	protected function setUp(): void {
		parent::setUp();

		$this->repository = new InMemoryObjectMetaRepository();
		$this->store      = new UserProfileFieldSurface( repository: $this->repository );
	}

	public function test_a_value_round_trips_under_the_resolved_storage_key(): void {
		$group = $this->group( new SettingsField( id: 'phone', type: 'text', label: 'Phone' ) );

		$this->store->set( $group, 3, 'phone', '555' );

		self::assertSame( '555', $this->store->get( $group, 3, 'phone' ) );
		self::assertSame( '555', $this->repository->get( 3, 'phone' ) );
		self::assertFalse( $this->store->has( $group, 4, 'phone' ) );
	}

	public function test_a_meta_key_override_is_the_byte_exact_storage_key(): void {
		$group = $this->group( new SettingsField( id: 'phone', type: 'text', label: 'Phone', meta_key: '_dws_phone' ) );

		$this->store->set( $group, 3, 'phone', '555' );

		self::assertSame( '555', $this->repository->get( 3, '_dws_phone' ) );
		self::assertFalse( $this->repository->has( 3, 'phone' ) );
	}

	public function test_get_returns_the_caller_fallback_never_the_field_default_when_nothing_is_stored(): void {
		$group = $this->group( new SettingsField( id: 'phone', type: 'text', label: 'Phone', default_value: 'declared-default' ) );

		self::assertSame( 'fallback', $this->store->get( $group, 3, 'phone', 'fallback' ) );
	}

	public function test_set_stores_a_checkbox_in_its_canonical_yes_no_form(): void {
		$group = $this->group( new SettingsField( id: 'flag', type: 'checkbox', label: 'Flag' ) );

		$this->store->set( $group, 3, 'flag', true );
		self::assertSame( 'yes', $this->repository->get( 3, 'flag' ) );

		$this->store->set( $group, 3, 'flag', false );
		self::assertSame( 'no', $this->repository->get( 3, 'flag' ) );
		self::assertTrue( $this->store->has( $group, 3, 'flag' ) );
	}

	public function test_set_revokes_the_key_for_a_value_a_form_save_would_not_store(): void {
		$group = $this->group( new SettingsField( id: 'phone', type: 'text', label: 'Phone' ) );

		$this->store->set( $group, 3, 'phone', '555' );
		$this->store->set( $group, 3, 'phone', '' );

		self::assertFalse( $this->store->has( $group, 3, 'phone' ) );
	}

	public function test_delete_removes_a_stored_value_and_reports_a_missing_one(): void {
		$group = $this->group( new SettingsField( id: 'phone', type: 'text', label: 'Phone' ) );

		$this->store->set( $group, 3, 'phone', '555' );

		self::assertTrue( $this->store->delete( $group, 3, 'phone' ) );
		self::assertFalse( $this->store->has( $group, 3, 'phone' ) );
		self::assertFalse( $this->store->delete( $group, 3, 'phone' ) );
	}

	public function test_a_field_the_group_does_not_declare_is_rejected(): void {
		$this->expectException( InvalidSettingsFieldException::class );

		$this->store->delete( $this->group( new SettingsField( id: 'phone', type: 'text', label: 'Phone' ) ), 3, 'missing' );
	}

	public function test_meta_keys_enumerates_the_resolved_storage_keys(): void {
		$group = $this->group(
			new SettingsField( id: 'phone', type: 'text', label: 'Phone' ),
			new SettingsField( id: 'badge', type: 'text', label: 'Badge', meta_key: '_dws_badge' ),
		);

		self::assertSame( array( 'phone', '_dws_badge' ), $this->store->meta_keys( $group ) );
	}

	private function group( SettingsField ...$fields ): FieldGroup {
		$fields = \array_values( $fields );

		return new FieldGroup(
			id: 'dws_profile_group',
			title: 'Group',
			fields_provider: static fn ( int $object_id ): array => $fields,
		);
	}
}
