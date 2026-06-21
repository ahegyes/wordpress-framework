<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Tests\Unit;

use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsPage;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsSection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( SettingsPage::class )]
#[UsesClass( SettingsSection::class )]
final class SettingsPageTest extends TestCase {
	public function test_construction_round_trips_every_value(): void {
		$sections = array( new SettingsSection( id: 'general', title: 'General', fields: array() ) );
		$page     = new SettingsPage(
			slug: 'my-plugin',
			page_title: 'My Plugin Settings',
			menu_title: 'My Plugin',
			capability: 'manage_options',
			location: 'options-general.php',
			sections: $sections,
		);

		self::assertSame( 'my-plugin', $page->slug );
		self::assertSame( 'My Plugin Settings', $page->page_title );
		self::assertSame( 'My Plugin', $page->menu_title );
		self::assertSame( 'manage_options', $page->capability );
		self::assertSame( 'options-general.php', $page->location );
		self::assertSame( $sections, $page->sections );
	}

	public function test_location_defaults_to_null(): void {
		$page = new SettingsPage(
			slug: 'my-plugin',
			page_title: 'My Plugin Settings',
			menu_title: 'My Plugin',
			capability: 'manage_options',
			sections: array(),
		);

		self::assertNull( $page->location );
	}
}
