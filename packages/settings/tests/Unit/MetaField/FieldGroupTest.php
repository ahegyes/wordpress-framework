<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Tests\Unit\MetaField;

use DeepWebSolutions\Framework\Settings\MetaField\Exceptions\InvalidFieldGroupException;
use DeepWebSolutions\Framework\Settings\MetaField\ValueObjects\FieldGroup;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesFunction;
use PHPUnit\Framework\TestCase;

#[CoversClass( FieldGroup::class )]
#[UsesFunction( 'DeepWebSolutions\Framework\Settings\Schema\is_valid_identifier' )]
final class FieldGroupTest extends TestCase {
	public function test_minimal_construction_round_trips(): void {
		$provider = static fn ( int $object_id ): array => array();
		$group    = new FieldGroup(
			id: 'lpm_unlock',
			title: 'Unlock',
			fields_provider: $provider,
		);

		self::assertSame( 'lpm_unlock', $group->id );
		self::assertSame( 'Unlock', $group->title );
		self::assertInstanceOf( \Closure::class, $group->fields_provider );
		self::assertNull( $group->render );
		self::assertNull( $group->save );
	}

	public function test_an_id_outside_the_charset_throws(): void {
		$this->expectException( InvalidFieldGroupException::class );

		new FieldGroup(
			id: 'Bad Id!',
			title: 'Group',
			fields_provider: static fn ( int $object_id ): array => array(),
		);
	}

	public function test_callables_are_normalized_to_closures(): void {
		$source = new class() {
			/**
			 * @return list<\DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsField>
			 */
			public function fields( int $object_id ): array {
				return array();
			}
		};
		$group = new FieldGroup(
			id: 'g',
			title: 'G',
			fields_provider: array( $source, 'fields' ),
			render: static fn ( int $object_id ): string => '',
			save: 'strlen',
		);

		self::assertInstanceOf( \Closure::class, $group->fields_provider );
		self::assertInstanceOf( \Closure::class, $group->render );
		self::assertInstanceOf( \Closure::class, $group->save );
	}
}
