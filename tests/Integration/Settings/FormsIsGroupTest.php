<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Settings;

use DeepWebSolutions\Framework\Settings\Forms;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( Forms::class )]
final class FormsIsGroupTest extends TestCase {
	public static function fields(): array {
		return array(
			'radio'                 => array( array( 'type' => 'radio' ), true ),
			'checkboxes'            => array( array( 'type' => 'checkboxes' ), true ),
			'checkbox-with-desc'    => array( self::field( 'checkbox', 'Enable it' ), true ),
			'checkbox-without-desc' => array( array( 'type' => 'checkbox' ), false ),
			'checkbox-empty-desc'   => array( self::field( 'checkbox', '' ), false ),
			'select'                => array( array( 'type' => 'select' ), false ),
			'text-with-desc'        => array( self::field( 'text', 'Help.' ), false ),
		);
	}

	#[DataProvider( 'fields' )]
	public function test_a_field_is_a_group_when_it_renders_in_a_fieldset_its_title_names( array $field, bool $group ): void {
		self::assertSame( $group, Forms::is_group( $field ) );
	}

	protected static function field( string $type, string $desc ): array {
		return array(
			'type' => $type,
			'desc' => $desc,
		);
	}
}
