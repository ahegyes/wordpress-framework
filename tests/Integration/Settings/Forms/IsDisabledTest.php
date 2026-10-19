<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Settings\Forms;

use DeepWebSolutions\Framework\Settings\Forms;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( Forms::class )]
final class IsDisabledTest extends TestCase {
	public static function fields(): array {
		return array(
			'custom-attribute-on-text'       => array( 'text', array( 'custom_attributes' => array( 'disabled' => 'disabled' ) ), true ),
			'custom-attribute-valued-false'  => array( 'select', array( 'custom_attributes' => array( 'disabled' => false ) ), true ),
			'custom-attribute-valued-empty'  => array( 'radio', array( 'custom_attributes' => array( 'disabled' => '' ) ), true ),
			'custom-attribute-in-upper-case' => array( 'checkboxes', array( 'custom_attributes' => array( 'DISABLED' => 'disabled' ) ), true ),
			'top-level-on-checkbox'          => array( 'checkbox', array( 'disabled' => true ), true ),
			'top-level-false-on-checkbox'    => array( 'checkbox', array( 'disabled' => false ), false ),
			'top-level-list-on-radio'        => array( 'radio', array( 'disabled' => array( 'a', 'b' ) ), false ),
			'top-level-on-text'              => array( 'text', array( 'disabled' => true ), false ),
			'top-level-on-checkboxes'        => array( 'checkboxes', array( 'disabled' => true ), false ),
			'other-custom-attributes-only'   => array( 'text', array( 'custom_attributes' => array( 'readonly' => 'readonly' ) ), false ),
			'no-disabling-key'               => array( 'checkbox', array(), false ),
		);
	}

	#[DataProvider( 'fields' )]
	public function test_a_field_is_disabled_exactly_where_woocommerce_renders_it_disabled( string $type, array $keys, bool $disabled ): void {
		self::assertSame( $disabled, Forms::is_disabled( array( 'type' => $type ) + $keys ) );
	}
}
