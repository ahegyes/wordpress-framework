<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Tests\Unit\Backend;

use DeepWebSolutions\Framework\Settings\Backend\WordPressSettingsBackend;
use DeepWebSolutions\Framework\Settings\Schema\Field\FieldProcessor;
use DeepWebSolutions\Framework\Settings\Schema\Field\FieldRenderer;
use DeepWebSolutions\Framework\Settings\Schema\Options\OptionsResolver;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsField;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsPage;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsSection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesFunction;
use PHPUnit\Framework\TestCase;

#[CoversClass( WordPressSettingsBackend::class )]
#[UsesClass( SettingsField::class )]
#[UsesClass( SettingsSection::class )]
#[UsesClass( SettingsPage::class )]
#[UsesClass( FieldRenderer::class )]
#[UsesClass( FieldProcessor::class )]
#[UsesClass( OptionsResolver::class )]
#[UsesFunction( 'DeepWebSolutions\Framework\Shared\Identifier\is_valid_identifier' )]
#[UsesFunction( 'DeepWebSolutions\Framework\Settings\Schema\wordpress_field_type_sanitizers' )]
final class WordPressSettingsBackendTest extends TestCase {
	public function test_option_keys_derives_one_grouped_key_per_section_in_declaration_order(): void {
		$page = new SettingsPage(
			slug: 'dws-shop',
			page_title: 'Shop',
			menu_title: 'Shop',
			capability: 'manage_options',
			sections: array(
				new SettingsSection( 'general', 'General', array( new SettingsField( id: 'a', type: 'text', label: 'A' ) ) ),
				new SettingsSection( 'advanced', 'Advanced', array( new SettingsField( id: 'b', type: 'text', label: 'B' ) ) ),
			),
		);

		self::assertSame( array( 'dws-shop-general', 'dws-shop-advanced' ), ( new WordPressSettingsBackend() )->option_keys( $page ) );
	}

	public function test_option_keys_needs_no_registration_and_ignores_field_count(): void {
		// The enumerator serves the uninstall path, which runs without register_page(); the key set is
		// section-shaped, so a field-less section still owns exactly one grouped row.
		$page = new SettingsPage(
			slug: 'dws-shop',
			page_title: 'Shop',
			menu_title: 'Shop',
			capability: 'manage_options',
			sections: array(
				new SettingsSection( 'general', 'General', array() ),
			),
		);

		self::assertSame( array( 'dws-shop-general' ), ( new WordPressSettingsBackend() )->option_keys( $page ) );
	}

	public function test_option_keys_is_empty_for_a_sectionless_page(): void {
		// A sectionless page is never declared by a consumer (sections is a required parameter) but is a
		// real derived shape: a page projection whose sections were all dropped still enumerates cleanly.
		$page = new SettingsPage(
			slug: 'dws-shop',
			page_title: 'Shop',
			menu_title: 'Shop',
			capability: 'manage_options',
			sections: array(),
		);

		self::assertSame( array(), ( new WordPressSettingsBackend() )->option_keys( $page ) );
	}
}
