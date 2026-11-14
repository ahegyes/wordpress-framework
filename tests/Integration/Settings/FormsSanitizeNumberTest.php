<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Settings;

use DeepWebSolutions\Framework\Settings\Forms;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( Forms::class )]
final class FormsSanitizeNumberTest extends TestCase {
	public static function accepted(): array {
		return array(
			'canonical-leading-point'          => array( array(), '.5', '0.5' ),
			'canonical-exponent'               => array( array(), '1e3', '1000' ),
			'canonical-negative-exponent'      => array( array(), '25E-1', '2.5' ),
			'canonical-small-fraction'         => array( array(), '1.5e-7', '0.00000015' ),
			'canonical-trailing-zeros'         => array( array(), '2.50', '2.5' ),
			'canonical-leading-zeros'          => array( array(), '007', '7' ),
			'canonical-whole-decimal'          => array( array(), '3.0', '3' ),
			'canonical-negative-zero'          => array( array(), '-0.0', '0' ),
			'canonical-float'                  => array( array(), 2.0, '2' ),
			'cleared-below-its-min'            => array( array( 'min' => 1 ), '', '' ),
			'decimal-within-bounds-no-step'    => array(
				array(
					'min' => 1,
					'max' => 10,
				),
				'2.5',
				'2.5',
			),
			'at-its-min'                       => array( array( 'min' => '1' ), '1', '1' ),
			'at-its-max'                       => array( array( 'max' => '10' ), '10.0', '10' ),
			'whole-step-from-its-min'          => array(
				array(
					'min'  => 1,
					'step' => 2,
				),
				'5',
				'5',
			),
			'whole-step-from-zero'             => array( array( 'step' => 5 ), '-10', '-10' ),
			'whole-step-stores-integer-text'   => array( array( 'step' => '1' ), '4.000', '4' ),
			'whole-step-from-an-int'           => array( array( 'step' => 1 ), 7, '7' ),
			'decimal-step-checked-exactly'     => array( array( 'step' => '0.01' ), '0.3', '0.3' ),
			'decimal-step-from-a-decimal-min'  => array(
				array(
					'min'  => '0.5',
					'step' => '0.25',
				),
				'1.25',
				'1.25',
			),
			'step-any'                         => array( array( 'step' => 'any' ), '3.14159', '3.14159' ),
			'step-any-in-capitals'             => array( array( 'step' => 'ANY' ), '1.5', '1.5' ),
			'unparseable-min-ignored'          => array( array( 'min' => 'abc' ), '-5', '-5' ),
			'empty-max-ignored'                => array( array( 'max' => '' ), '99', '99' ),
			'zero-step-counts-as-one'          => array( array( 'step' => '0' ), '4', '4' ),
			'negative-step-counts-as-one'      => array( array( 'step' => -2 ), '3', '3' ),
			'unparseable-step-counts-as-one'   => array( array( 'step' => 'big' ), '6', '6' ),
			'whole-step-near-the-integer-edge' => array( array( 'step' => 1 ), '9223372036854775807', '9223372036854775807' ),
		);
	}

	#[DataProvider( 'accepted' )]
	public function test_a_number_within_its_bounds_and_on_its_grid_stores_as_canonical_text( array $attributes, mixed $raw, string $expected ): void {
		self::assertSame( $expected, self::sanitize( $attributes, $raw ) );
	}

	public static function rejected(): array {
		return array(
			'below-its-min'                   => array( array( 'min' => 1 ), '0', 'number_below_min', 'Enter a number of at least 1.' ),
			'below-a-decimal-min'             => array( array( 'min' => '0.50' ), '0.25', 'number_below_min', 'Enter a number of at least 0.5.' ),
			'below-a-min-named-in-capitals'   => array( array( 'MIN' => 5 ), '1', 'number_below_min', 'Enter a number of at least 5.' ),
			'above-its-max'                   => array( array( 'max' => 10 ), '10.5', 'number_above_max', 'Enter a number of at most 10.' ),
			'off-a-whole-step'                => array( array( 'step' => 1 ), '2.5', 'number_off_step', 'Enter a number in steps of 1 from 0.' ),
			'off-a-step-from-its-min'         => array(
				array(
					'min'  => 1,
					'step' => 2,
				),
				'4',
				'number_off_step',
				'Enter a number in steps of 2 from 1.',
			),
			'off-a-decimal-step'              => array( array( 'step' => '0.01' ), '0.305', 'number_off_step', 'Enter a number in steps of 0.01 from 0.' ),
			'off-the-step-a-zero-step-means'  => array( array( 'step' => '0' ), '4.5', 'number_off_step', 'Enter a number in steps of 1 from 0.' ),
			'beyond-the-integer-range'        => array( array( 'step' => 1 ), '1e19', 'invalid_number', 'Enter a number, such as 12 or 3.5.' ),
			'beyond-the-range-at-its-decimal' => array( array( 'step' => '0.01' ), '100000000000000000', 'invalid_number', 'Enter a number, such as 12 or 3.5.' ),
			'too-small-to-hold'               => array( array(), '1e-400', 'invalid_number', 'Enter a number, such as 12 or 3.5.' ),
		);
	}

	#[DataProvider( 'rejected' )]
	public function test_a_number_outside_its_bounds_or_off_its_grid_is_rejected_naming_what_it_missed( array $attributes, mixed $raw, string $code, string $message ): void {
		$result = self::sanitize( $attributes, $raw );

		self::assertInstanceOf( \WP_Error::class, $result );
		self::assertSame( $code, $result->get_error_code() );
		self::assertSame( $message, $result->get_error_message() );
	}

	protected static function sanitize( array $attributes, mixed $raw ): mixed {
		$field  = array(
			'type'              => 'number',
			'id'                => 'key',
			'custom_attributes' => $attributes,
		);
		$result = Forms::sanitize_fields( array( $field ), array( 'key' => $raw ) );

		return $result['errors']['key'] ?? $result['values']['key'];
	}
}
