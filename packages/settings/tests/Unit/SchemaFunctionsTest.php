<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Tests\Unit;

use DeepWebSolutions\Framework\Settings\Schema\Exceptions\DuplicateSettingsFieldException;
use DeepWebSolutions\Framework\Settings\Schema\Exceptions\DuplicateSettingsSectionException;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsField;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsPage;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsSection;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function DeepWebSolutions\Framework\Settings\Schema\assert_unique_section_and_field_ids;
use function DeepWebSolutions\Framework\Settings\Schema\filter_field_attributes;
use function DeepWebSolutions\Framework\Settings\Schema\is_checkbox_checked;
use function DeepWebSolutions\Framework\Settings\Schema\is_valid_identifier;

#[CoversFunction( 'DeepWebSolutions\Framework\Settings\Schema\assert_unique_section_and_field_ids' )]
#[CoversFunction( 'DeepWebSolutions\Framework\Settings\Schema\filter_field_attributes' )]
#[CoversFunction( 'DeepWebSolutions\Framework\Settings\Schema\is_valid_identifier' )]
#[CoversFunction( 'DeepWebSolutions\Framework\Settings\Schema\is_checkbox_checked' )]
#[UsesClass( SettingsField::class )]
#[UsesClass( SettingsSection::class )]
#[UsesClass( SettingsPage::class )]
#[UsesClass( DuplicateSettingsSectionException::class )]
#[UsesClass( DuplicateSettingsFieldException::class )]
final class SchemaFunctionsTest extends TestCase {
	public function test_keeps_well_formed_non_event_attribute_names(): void {
		$kept = filter_field_attributes(
			array( 'class' => 'widefat', 'data-foo' => 'bar', 'min' => '0', 'step' => '1' ),
		);

		self::assertSame(
			array( 'class' => 'widefat', 'data-foo' => 'bar', 'min' => '0', 'step' => '1' ),
			$kept,
		);
	}

	public function test_keeps_a_mixed_case_attribute_name(): void {
		self::assertSame(
			array( 'data-Foo' => 'bar' ),
			filter_field_attributes( array( 'data-Foo' => 'bar' ) ),
		);
	}

	#[DataProvider( 'event_handler_names' )]
	public function test_drops_every_on_prefixed_name( string $name ): void {
		self::assertSame(
			array(),
			filter_field_attributes( array( $name => 'evil()' ) ),
		);
	}

	/**
	 * Every name beginning with "on" is dropped by prefix, including ones that are not real handlers
	 * (once, on-call) — the strict prefix block is conservative on purpose.
	 *
	 * @return array<string, array{string}>
	 */
	public static function event_handler_names(): array {
		return array(
			'onclick'      => array( 'onclick' ),
			'onmouseover'  => array( 'onmouseover' ),
			'onfocus'      => array( 'onfocus' ),
			'once'         => array( 'once' ),
			'on-call'      => array( 'on-call' ),
			'mixed-case ON' => array( 'ONload' ),
		);
	}

	#[DataProvider( 'malformed_attribute_names' )]
	public function test_drops_malformed_attribute_names( string $name ): void {
		self::assertSame(
			array(),
			filter_field_attributes( array( $name => 'x' ) ),
		);
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function malformed_attribute_names(): array {
		return array(
			'empty'            => array( '' ),
			'leading digit'    => array( '1bad' ),
			'leading hyphen'   => array( '-bad' ),
			'underscore'       => array( 'data_foo' ),
			'space-separated'  => array( 'autofocus onfocus' ),
			'dot'              => array( 'data.foo' ),
		);
	}

	public function test_keeps_valid_drops_invalid_in_one_pass(): void {
		// The dropped names precede the kept one so a skip that fell through to break would drop it too.
		$kept = filter_field_attributes(
			array( 'onclick' => 'evil()', '1bad' => 'x', 'min' => '0' ),
		);

		self::assertSame( array( 'min' => '0' ), $kept );
	}

	#[DataProvider( 'valid_identifiers' )]
	public function test_accepts_valid_identifiers( string $identifier ): void {
		self::assertTrue( is_valid_identifier( $identifier ) );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function valid_identifiers(): array {
		return array(
			'single letter'   => array( 'a' ),
			'word'            => array( 'field' ),
			'with digit'      => array( 'field2' ),
			'with underscore' => array( 'my_field' ),
			'with hyphen'     => array( 'my-field' ),
			'mixed'           => array( 'a1_b-2' ),
		);
	}

	#[DataProvider( 'invalid_identifiers' )]
	public function test_rejects_invalid_identifiers( string $identifier ): void {
		self::assertFalse( is_valid_identifier( $identifier ) );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function invalid_identifiers(): array {
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

	#[DataProvider( 'checkbox_truth_matrix' )]
	public function test_checkbox_truth_rule( mixed $value, bool $expected ): void {
		self::assertSame( $expected, is_checkbox_checked( $value ) );
	}

	/**
	 * The canonical checkbox truth rule: only true, 1, '1', and 'yes' are checked. Everything else,
	 * including the previously-truthy 'no'/'off'/'false'/arbitrary strings, is unchecked.
	 *
	 * @return array<string, array{mixed, bool}>
	 */
	public static function checkbox_truth_matrix(): array {
		return array(
			'bool true'          => array( true, true ),
			'int 1'              => array( 1, true ),
			'string 1'          => array( '1', true ),
			'string yes'        => array( 'yes', true ),
			'bool false'         => array( false, false ),
			'int 0'              => array( 0, false ),
			'string 0'          => array( '0', false ),
			'string no'         => array( 'no', false ),
			'string off'        => array( 'off', false ),
			'string false'      => array( 'false', false ),
			'string on'         => array( 'on', false ),
			'arbitrary string'   => array( 'anything', false ),
			'empty string'       => array( '', false ),
			'null'               => array( null, false ),
			'array'              => array( array( 'yes' ), false ),
			'int 2'              => array( 2, false ),
		);
	}

	public function test_assert_unique_ids_accepts_unique_section_and_field_ids(): void {
		$this->expectNotToPerformAssertions();

		assert_unique_section_and_field_ids(
			new SettingsPage(
				slug: 'dws-test',
				page_title: 'Test',
				menu_title: 'Test',
				capability: 'manage_options',
				sections: array(
					new SettingsSection( 'general', 'General', array( new SettingsField( id: 'a', type: 'text', label: 'A' ) ) ),
					new SettingsSection( 'advanced', 'Advanced', array( new SettingsField( id: 'b', type: 'text', label: 'B' ) ) ),
				),
			),
		);
	}

	public function test_assert_unique_ids_rejects_a_duplicate_section_id(): void {
		$this->expectException( DuplicateSettingsSectionException::class );

		assert_unique_section_and_field_ids(
			new SettingsPage(
				slug: 'dws-test',
				page_title: 'Test',
				menu_title: 'Test',
				capability: 'manage_options',
				sections: array(
					new SettingsSection( 'general', 'General', array( new SettingsField( id: 'a', type: 'text', label: 'A' ) ) ),
					new SettingsSection( 'general', 'Again', array( new SettingsField( id: 'b', type: 'text', label: 'B' ) ) ),
				),
			),
		);
	}

	public function test_assert_unique_ids_rejects_a_duplicate_field_id_across_sections(): void {
		$this->expectException( DuplicateSettingsFieldException::class );

		assert_unique_section_and_field_ids(
			new SettingsPage(
				slug: 'dws-test',
				page_title: 'Test',
				menu_title: 'Test',
				capability: 'manage_options',
				sections: array(
					new SettingsSection( 'general', 'General', array( new SettingsField( id: 'dup', type: 'text', label: 'A' ) ) ),
					new SettingsSection( 'advanced', 'Advanced', array( new SettingsField( id: 'dup', type: 'text', label: 'B' ) ) ),
				),
			),
		);
	}

	public function test_assert_unique_ids_reports_a_duplicate_section_before_a_duplicate_field(): void {
		// A page carrying both a repeated section id and a repeated field id reports the section first.
		$this->expectException( DuplicateSettingsSectionException::class );

		assert_unique_section_and_field_ids(
			new SettingsPage(
				slug: 'dws-test',
				page_title: 'Test',
				menu_title: 'Test',
				capability: 'manage_options',
				sections: array(
					new SettingsSection( 'general', 'General', array( new SettingsField( id: 'dup', type: 'text', label: 'A' ) ) ),
					new SettingsSection( 'general', 'Again', array( new SettingsField( id: 'dup', type: 'text', label: 'B' ) ) ),
				),
			),
		);
	}
}
