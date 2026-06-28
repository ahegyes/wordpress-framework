<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Tests\Unit;

use DeepWebSolutions\Framework\Settings\Schema\Exceptions\InvalidSettingsSectionException;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsField;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsSection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesFunction;
use PHPUnit\Framework\TestCase;

#[CoversClass( SettingsSection::class )]
#[UsesClass( SettingsField::class )]
#[UsesFunction( 'DeepWebSolutions\Framework\Settings\Schema\is_valid_identifier' )]
final class SettingsSectionTest extends TestCase {
	public function test_construction_round_trips_id_title_and_fields(): void {
		$fields  = array(
			new SettingsField( id: 'a', type: 'text', label: 'A' ),
			new SettingsField( id: 'b', type: 'text', label: 'B' ),
		);
		$section = new SettingsSection( id: 'general', title: 'General', fields: $fields );

		self::assertSame( 'general', $section->id );
		self::assertSame( 'General', $section->title );
		self::assertSame( $fields, $section->fields );
	}

	public function test_accepts_an_empty_field_list(): void {
		$section = new SettingsSection( id: 'empty', title: 'Empty', fields: array() );

		self::assertSame( array(), $section->fields );
	}

	#[DataProvider( 'valid_ids' )]
	public function test_accepts_valid_ids( string $valid_id ): void {
		$section = new SettingsSection( id: $valid_id, title: 'T', fields: array() );

		self::assertSame( $valid_id, $section->id );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function valid_ids(): array {
		return array(
			'single letter'   => array( 'a' ),
			'with digit'      => array( 'section2' ),
			'with underscore' => array( 'my_section' ),
			'with hyphen'     => array( 'my-section' ),
		);
	}

	#[DataProvider( 'invalid_ids' )]
	public function test_rejects_invalid_ids( string $invalid_id ): void {
		$this->expectException( InvalidSettingsSectionException::class );

		new SettingsSection( id: $invalid_id, title: 'T', fields: array() );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function invalid_ids(): array {
		return array(
			'empty'              => array( '' ),
			'leading digit'      => array( '1section' ),
			'leading hyphen'     => array( '-section' ),
			'leading underscore' => array( '_section' ),
			'uppercase'          => array( 'Section' ),
			'space'              => array( 'my section' ),
			'dot'                => array( 'my.section' ),
		);
	}
}
