<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Tests\Unit;

use DeepWebSolutions\Framework\Settings\Schema\Exceptions\InvalidSettingsPageException;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsPage;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsSection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesFunction;
use PHPUnit\Framework\TestCase;

#[CoversClass( SettingsPage::class )]
#[UsesClass( SettingsSection::class )]
#[UsesFunction( 'DeepWebSolutions\Framework\Shared\Identifier\is_valid_identifier' )]
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

	#[DataProvider( 'valid_slugs' )]
	public function test_accepts_valid_slugs( string $valid_slug ): void {
		$page = new SettingsPage(
			slug: $valid_slug,
			page_title: 'T',
			menu_title: 'T',
			capability: 'manage_options',
		);

		self::assertSame( $valid_slug, $page->slug );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function valid_slugs(): array {
		return array(
			'single letter'   => array( 'a' ),
			'with digit'      => array( 'page2' ),
			'with underscore' => array( 'my_page' ),
			'with hyphen'     => array( 'my-plugin' ),
		);
	}

	#[DataProvider( 'invalid_slugs' )]
	public function test_rejects_invalid_slugs( string $invalid_slug ): void {
		$this->expectException( InvalidSettingsPageException::class );

		new SettingsPage(
			slug: $invalid_slug,
			page_title: 'T',
			menu_title: 'T',
			capability: 'manage_options',
		);
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function invalid_slugs(): array {
		return array(
			'empty'              => array( '' ),
			'leading digit'      => array( '1page' ),
			'leading hyphen'     => array( '-page' ),
			'leading underscore' => array( '_page' ),
			'uppercase'          => array( 'Page' ),
			'space'              => array( 'my page' ),
			'slash'              => array( 'my/page' ),
		);
	}
}
