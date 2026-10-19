<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Settings\Forms;

use DeepWebSolutions\Framework\Settings\Forms;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( Forms::class )]
final class SanitizeFieldsTest extends TestCase {
	public function test_only_defined_present_and_enabled_fields_get_an_entry(): void {
		$result = Forms::sanitize_fields(
			array(
				array(
					'id'   => 'name',
					'type' => 'text',
				),
				array(
					'id'       => 'locked',
					'type'     => 'checkbox',
					'disabled' => true,
				),
				array(
					'id'   => 'absent',
					'type' => 'text',
				),
				array(
					'type'  => 'title',
					'title' => 'Section',
				),
			),
			array(
				'name'    => ' Acme ',
				'locked'  => 'yes',
				'outside' => 'x',
			)
		);

		self::assertSame(
			array(
				'values' => array( 'name' => 'Acme' ),
				'errors' => array(),
			),
			$result
		);
	}

	public function test_rejections_are_returned_apart_from_accepted_values(): void {
		$result = Forms::sanitize_fields(
			array(
				array(
					'id'      => 'mode',
					'type'    => 'select',
					'options' => array( 'a' => 'A' ),
				),
				array(
					'id'       => 'code',
					'type'     => 'text',
					'validate' => static fn ( mixed $raw ): \WP_Error => new \WP_Error( 'acme_code', 'The code is invalid.' ),
				),
				array(
					'id'   => 'days',
					'type' => 'checkboxes',
				),
			),
			array(
				'mode' => 'z',
				'code' => 'x',
				'days' => '',
			)
		);

		self::assertSame( array( 'days' => array() ), $result['values'] );
		self::assertSame( array( 'mode', 'code' ), \array_keys( $result['errors'] ) );
		self::assertSame( 'invalid_choice', $result['errors']['mode']->get_error_code() );
		self::assertSame( 'The code is invalid.', $result['errors']['code']->get_error_message() );
	}

	public function test_a_present_null_is_sanitized_rather_than_read_as_absent(): void {
		$result = Forms::sanitize_fields(
			array(
				array(
					'id'   => 'name',
					'type' => 'text',
				),
			),
			array( 'name' => null )
		);

		self::assertSame( array(), $result['values'] );
		self::assertSame( 'invalid_value', $result['errors']['name']->get_error_code() );
	}

	public function test_merging_the_values_onto_the_stored_row_keeps_every_untouched_entry(): void {
		$stored = array(
			'name'    => 'Old',
			'absent'  => 'Kept',
			'removed' => 'Kept too',
		);
		$result = Forms::sanitize_fields(
			array(
				array(
					'id'   => 'name',
					'type' => 'text',
				),
				array(
					'id'   => 'absent',
					'type' => 'text',
				),
			),
			array( 'name' => 'New' )
		);

		self::assertSame(
			array(
				'name'    => 'New',
				'absent'  => 'Kept',
				'removed' => 'Kept too',
			),
			\array_replace( $stored, $result['values'] )
		);
	}
}
