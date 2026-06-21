<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Tests\Unit;

use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsField;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsSection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( SettingsSection::class )]
#[UsesClass( SettingsField::class )]
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
}
