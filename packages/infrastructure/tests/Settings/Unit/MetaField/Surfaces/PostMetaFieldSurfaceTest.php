<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Tests\Unit\MetaField\Surfaces;

use DeepWebSolutions\Framework\Settings\MetaField\ObjectFieldForm;
use DeepWebSolutions\Framework\Settings\MetaField\Surfaces\PostMetaFieldSurface;
use DeepWebSolutions\Framework\Settings\MetaField\ValueObjects\FieldGroup;
use DeepWebSolutions\Framework\Settings\Schema\Exceptions\DuplicateSettingsFieldException;
use DeepWebSolutions\Framework\Settings\Schema\Field\FieldProcessor;
use DeepWebSolutions\Framework\Settings\Schema\Field\FieldRenderer;
use DeepWebSolutions\Framework\Settings\Schema\Options\OptionsResolver;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsField;
use DeepWebSolutions\Framework\Storage\ObjectMeta\ObjectMetaRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesFunction;

#[CoversClass( PostMetaFieldSurface::class )]
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
final class PostMetaFieldSurfaceTest extends ObjectFieldSurfaceContractTestCase {
	protected function make_surface( ObjectMetaRepositoryInterface $repository ): PostMetaFieldSurface {
		return new PostMetaFieldSurface( repository: $repository );
	}

	public function test_two_fields_sharing_an_effective_storage_key_are_rejected(): void {
		$this->expectException( DuplicateSettingsFieldException::class );

		$this->surface->get(
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

		$this->surface->set( $group, 7, 'note', 'hello' );

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

		self::assertSame( array( 'note', '_dws_color' ), $this->surface->meta_keys( $group ) );
	}
}
