<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Tests\Unit;

use DeepWebSolutions\Framework\Settings\Schema\Exceptions\InvalidCustomFieldTypeException;
use DeepWebSolutions\Framework\Settings\Schema\Field\FieldType;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\CustomFieldType;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsField;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesFunction;
use PHPUnit\Framework\TestCase;

#[CoversClass( CustomFieldType::class )]
#[UsesClass( FieldType::class )]
#[UsesClass( SettingsField::class )]
#[UsesFunction( 'DeepWebSolutions\Framework\Shared\Identifier\is_valid_identifier' )]
final class CustomFieldTypeTest extends TestCase {
	public function test_a_valid_token_round_trips(): void {
		$render = static fn ( SettingsField $field, mixed $value, string $name ): string => '<custom />';
		$type   = new CustomFieldType( type: 'color_picker', render: $render );

		self::assertSame( 'color_picker', $type->type );
	}

	public function test_the_callable_is_normalized_to_a_closure(): void {
		$type = new CustomFieldType( type: 'my_type', render: array( $this, 'render_stub' ) );

		self::assertSame(
			'stub',
			( $type->render )( new SettingsField( id: 'f', type: 'my_type', label: 'F' ), null, 'f' ),
		);
	}

	public function test_a_closure_render_is_preserved(): void {
		$closure = static fn ( SettingsField $field, mixed $value, string $name ): string => '<x />';
		$type    = new CustomFieldType( type: 'my_type', render: $closure );

		self::assertSame(
			'<x />',
			( $type->render )( new SettingsField( id: 'f', type: 'my_type', label: 'F' ), null, 'f' ),
		);
	}

	#[DataProvider( 'valid_types' )]
	public function test_accepts_valid_type_tokens( string $valid_type ): void {
		$type = new CustomFieldType(
			type: $valid_type,
			render: static fn ( SettingsField $field, mixed $value, string $name ): string => '',
		);

		self::assertSame( $valid_type, $type->type );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function valid_types(): array {
		return array(
			'single letter'   => array( 'a' ),
			'word'            => array( 'colorpicker' ),
			'with digit'      => array( 'type2' ),
			'with underscore' => array( 'color_picker' ),
			'with hyphen'     => array( 'color-picker' ),
		);
	}

	#[DataProvider( 'invalid_types' )]
	public function test_rejects_an_invalid_type_token( string $invalid_type ): void {
		$this->expectException( InvalidCustomFieldTypeException::class );

		new CustomFieldType(
			type: $invalid_type,
			render: static fn ( SettingsField $field, mixed $value, string $name ): string => '',
		);
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function invalid_types(): array {
		return array(
			'empty'              => array( '' ),
			'leading digit'      => array( '1type' ),
			'leading hyphen'     => array( '-type' ),
			'leading underscore' => array( '_type' ),
			'uppercase'          => array( 'Type' ),
			'space'              => array( 'my type' ),
			'dot'                => array( 'my.type' ),
		);
	}

	#[DataProvider( 'builtin_types' )]
	public function test_rejects_a_builtin_field_type_token( string $builtin_type ): void {
		$this->expectException( InvalidCustomFieldTypeException::class );

		new CustomFieldType(
			type: $builtin_type,
			render: static fn ( SettingsField $field, mixed $value, string $name ): string => '',
		);
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function builtin_types(): array {
		$cases = array();
		foreach ( FieldType::cases() as $case ) {
			$cases[ $case->value ] = array( $case->value );
		}

		return $cases;
	}

	public function render_stub( SettingsField $field, mixed $value, string $name ): string {
		return 'stub';
	}
}
