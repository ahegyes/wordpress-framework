<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Tests\Unit;

use DeepWebSolutions\Framework\Settings\Schema\Exceptions\InvalidSettingsFieldException;
use DeepWebSolutions\Framework\Settings\Schema\Options\SettingsOptionsProviderInterface;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsField;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesFunction;
use PHPUnit\Framework\TestCase;

#[CoversClass( SettingsField::class )]
#[UsesFunction( 'DeepWebSolutions\Framework\Shared\Identifier\is_valid_identifier' )]
final class SettingsFieldTest extends TestCase {
	public function test_minimal_construction_exposes_documented_defaults(): void {
		$field = new SettingsField( id: 'my_field', type: 'text', label: 'My Field' );

		self::assertSame( 'my_field', $field->id );
		self::assertSame( 'text', $field->type );
		self::assertSame( 'My Field', $field->label );
		self::assertNull( $field->default_value );
		self::assertNull( $field->sanitize );
		self::assertNull( $field->validate );
		self::assertNull( $field->capability );
		self::assertFalse( $field->show_in_rest );
		self::assertFalse( $field->autoload );
		self::assertNull( $field->position );
		self::assertSame( array(), $field->options );
		self::assertSame( array(), $field->attributes );
		self::assertNull( $field->meta_key );
		self::assertNull( $field->description );
	}

	public function test_full_construction_round_trips_every_value(): void {
		$options = array( 'a' => 'A' );
		$field   = new SettingsField(
			id: 'field-2',
			type: 'select',
			label: 'Field 2',
			default_value: 'a',
			sanitize: 'trim',
			validate: 'is_string',
			capability: 'manage_options',
			show_in_rest: true,
			autoload: true,
			position: 5,
			options: $options,
			attributes: array( 'class' => 'widefat' ),
			meta_key: '_my_meta',
			description: 'Helpful hint',
		);

		self::assertSame( 'a', $field->default_value );
		self::assertSame( 'manage_options', $field->capability );
		self::assertTrue( $field->show_in_rest );
		self::assertTrue( $field->autoload );
		self::assertSame( 5, $field->position );
		self::assertSame( $options, $field->options );
		self::assertSame( array( 'class' => 'widefat' ), $field->attributes );
		self::assertSame( '_my_meta', $field->meta_key );
		self::assertSame( 'Helpful hint', $field->description );
	}

	public function test_string_callable_sanitize_is_normalized_to_a_closure(): void {
		$field = new SettingsField( id: 'f', type: 'text', label: 'F', sanitize: 'trim' );

		self::assertInstanceOf( \Closure::class, $field->sanitize );
		self::assertSame( 'x', ( $field->sanitize )( '  x  ' ) );
	}

	public function test_string_callable_validate_is_normalized_to_a_closure(): void {
		$field = new SettingsField( id: 'f', type: 'text', label: 'F', validate: 'is_string' );

		self::assertInstanceOf( \Closure::class, $field->validate );
		self::assertTrue( ( $field->validate )( 'x' ) );
	}

	public function test_array_callable_sanitize_is_normalized_to_a_closure(): void {
		$cleaner = new class() {
			public function clean( string $value ): string {
				return \trim( $value );
			}
		};
		$field   = new SettingsField( id: 'f', type: 'text', label: 'F', sanitize: array( $cleaner, 'clean' ) );

		self::assertInstanceOf( \Closure::class, $field->sanitize );
		self::assertSame( 'y', ( $field->sanitize )( ' y ' ) );
	}

	public function test_closure_sanitize_is_preserved(): void {
		$closure = static fn ( mixed $value ): mixed => $value;
		$field   = new SettingsField( id: 'f', type: 'text', label: 'F', sanitize: $closure );

		self::assertInstanceOf( \Closure::class, $field->sanitize );
	}

	public function test_options_accepts_an_array(): void {
		$field = new SettingsField( id: 'f', type: 'select', label: 'F', options: array( 'k' => 'V' ) );

		self::assertSame( array( 'k' => 'V' ), $field->options );
	}

	public function test_options_accepts_a_closure(): void {
		$closure = static fn (): array => array( 'k' => 'V' );
		$field   = new SettingsField( id: 'f', type: 'select', label: 'F', options: $closure );

		self::assertSame( $closure, $field->options );
	}

	public function test_options_accepts_a_provider(): void {
		$provider = new class() implements SettingsOptionsProviderInterface {
			public function get_options(): array {
				return array( 'k' => 'V' );
			}
		};
		$field    = new SettingsField( id: 'f', type: 'select', label: 'F', options: $provider );

		self::assertSame( $provider, $field->options );
	}

	#[DataProvider( 'valid_ids' )]
	public function test_accepts_valid_ids( string $valid_id ): void {
		$field = new SettingsField( id: $valid_id, type: 'text', label: 'L' );

		self::assertSame( $valid_id, $field->id );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function valid_ids(): array {
		return array(
			'single letter'   => array( 'a' ),
			'word'            => array( 'field' ),
			'with digit'      => array( 'field2' ),
			'with underscore' => array( 'my_field' ),
			'with hyphen'     => array( 'my-field' ),
			'mixed'           => array( 'a1_b-2' ),
		);
	}

	#[DataProvider( 'invalid_ids' )]
	public function test_rejects_invalid_ids( string $invalid_id ): void {
		$this->expectException( InvalidSettingsFieldException::class );

		new SettingsField( id: $invalid_id, type: 'text', label: 'L' );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function invalid_ids(): array {
		return array(
			'empty'              => array( '' ),
			'leading digit'      => array( '1field' ),
			'leading hyphen'     => array( '-field' ),
			'leading underscore' => array( '_field' ),
			'uppercase'          => array( 'Field' ),
			'space'              => array( 'my field' ),
			'dot'                => array( 'my.field' ),
			'slash'              => array( 'my/field' ),
			'trailing newline'   => array( "field\n" ),
		);
	}
}
