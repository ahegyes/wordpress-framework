<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Settings\Forms;

use DeepWebSolutions\Framework\Settings\Forms;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( Forms::class )]
final class SanitizeValueTest extends TestCase {
	protected const array OPTIONS = array(
		1 => 'One',
		2 => 'Two',
	);

	public static function accepted(): array {
		return array(
			'text-strips-tags-and-trims'        => array( 'text', '  <b>Hi</b> there ', 'Hi there' ),
			'text-stringifies-an-int'           => array( 'text', 5, '5' ),
			'textarea-keeps-newlines'           => array( 'textarea', "One\n<b>two</b>", "One\ntwo" ),
			'email-is-trimmed'                  => array( 'email', ' ops@example.com ', 'ops@example.com' ),
			'email-cleared'                     => array( 'email', '', '' ),
			'url-is-kept'                       => array( 'url', 'https://example.com/a?b=c', 'https://example.com/a?b=c' ),
			'url-gains-a-scheme'                => array( 'url', 'example.com', 'http://example.com' ),
			'url-cleared'                       => array( 'url', ' ', '' ),
			'number-integer'                    => array( 'number', '12', '12' ),
			'number-is-trimmed'                 => array( 'number', ' 4 ', '4' ),
			'number-negative-decimal'           => array( 'number', '-1.5', '-1.5' ),
			'number-leading-point'              => array( 'number', '.5', '.5' ),
			'number-exponent'                   => array( 'number', '1e3', '1e3' ),
			'number-cleared'                    => array( 'number', '', '' ),
			'number-from-an-int'                => array( 'number', 5, '5' ),
			'number-from-a-float'               => array( 'number', 1.5, '1.5' ),
			'checkbox-yes'                      => array( 'checkbox', 'yes', 'yes' ),
			'checkbox-one'                      => array( 'checkbox', '1', 'yes' ),
			'checkbox-true'                     => array( 'checkbox', true, 'yes' ),
			'checkbox-no'                       => array( 'checkbox', 'no', 'no' ),
			'checkbox-empty'                    => array( 'checkbox', '', 'no' ),
			'checkbox-false'                    => array( 'checkbox', false, 'no' ),
			'select-integer-key-as-post-string' => array( 'select', '2', '2' ),
			'select-integer-key-as-int'         => array( 'select', 2, '2' ),
			'radio-integer-key-as-post-string'  => array( 'radio', '1', '1' ),
			'multiselect-post-strings'          => array( 'multiselect', array( '1', '2' ), array( '1', '2' ) ),
			'multiselect-ints-as-strings'       => array( 'multiselect', array( 2, 1 ), array( '2', '1' ) ),
			'multiselect-deduplicated'          => array( 'multiselect', array( '2', '1', '2' ), array( '2', '1' ) ),
			'multiselect-reindexed'             => array( 'multiselect', array( 3 => '2' ), array( '2' ) ),
			'multiselect-cleared'               => array( 'multiselect', '', array() ),
			'checkboxes-post-strings'           => array( 'checkboxes', array( '1', '2' ), array( '1', '2' ) ),
			'checkboxes-cleared'                => array( 'checkboxes', '', array() ),
			'checkboxes-empty-array'            => array( 'checkboxes', array(), array() ),
		);
	}

	#[DataProvider( 'accepted' )]
	public function test_a_value_without_validate_is_sanitized_by_its_type( string $type, mixed $raw, mixed $expected ): void {
		self::assertSame( $expected, Forms::sanitize_value( self::field( $type ), $raw ) );
	}

	public static function rejected(): array {
		return array(
			'text-array'                    => array( 'text', array( 'a' ), 'invalid_value' ),
			'text-object'                   => array( 'text', new \stdClass(), 'invalid_value' ),
			'text-bool'                     => array( 'text', true, 'invalid_value' ),
			'text-null'                     => array( 'text', null, 'invalid_value' ),
			'textarea-array'                => array( 'textarea', array( 'a' ), 'invalid_value' ),
			'email-without-a-dotted-domain' => array( 'email', 'ops@localhost', 'invalid_email' ),
			'email-the-sanitizer-rewrites'  => array( 'email', 'jöhn@example.com', 'invalid_email' ),
			'email-not-an-address'          => array( 'email', 'not an email', 'invalid_email' ),
			'email-array'                   => array( 'email', array( 'ops@example.com' ), 'invalid_value' ),
			'url-script'                    => array( 'url', 'javascript:alert(1)', 'invalid_url' ),
			'url-mistyped-scheme'           => array( 'url', 'htps://example.com', 'invalid_url' ),
			'url-array'                     => array( 'url', array( 'https://example.com' ), 'invalid_value' ),
			'number-leading-plus'           => array( 'number', '+1', 'invalid_number' ),
			'number-trailing-point'         => array( 'number', '1.', 'invalid_number' ),
			'number-trailing-letters'       => array( 'number', '12abc', 'invalid_number' ),
			'number-decimal-comma'          => array( 'number', '1,5', 'invalid_number' ),
			'number-overflowing'            => array( 'number', '1e999', 'invalid_number' ),
			'number-infinite-float'         => array( 'number', \INF, 'invalid_number' ),
			'number-nan'                    => array( 'number', \NAN, 'invalid_number' ),
			'number-array'                  => array( 'number', array( '1' ), 'invalid_value' ),
			'checkbox-other-string'         => array( 'checkbox', 'maybe', 'invalid_choice' ),
			'checkbox-array'                => array( 'checkbox', array( 'yes' ), 'invalid_choice' ),
			'select-unknown-choice'         => array( 'select', '3', 'invalid_choice' ),
			'select-empty-choice'           => array( 'select', '', 'invalid_choice' ),
			'select-array'                  => array( 'select', array( '1' ), 'invalid_value' ),
			'radio-unknown-choice'          => array( 'radio', '3', 'invalid_choice' ),
			'multiselect-unknown-choice'    => array( 'multiselect', array( '1', '3' ), 'invalid_choice' ),
			'multiselect-string'            => array( 'multiselect', '1', 'invalid_value' ),
			'multiselect-nested-array'      => array( 'multiselect', array( array( '1' ) ), 'invalid_value' ),
			'checkboxes-unknown-choice'     => array( 'checkboxes', array( '3' ), 'invalid_choice' ),
			'unknown-type'                  => array( 'acme_color', '#fff', 'unknown_type' ),
		);
	}

	#[DataProvider( 'rejected' )]
	public function test_a_value_without_validate_is_rejected_with_an_empty_message( string $type, mixed $raw, string $code ): void {
		$result = Forms::sanitize_value( self::field( $type ), $raw );

		self::assertInstanceOf( \WP_Error::class, $result );
		self::assertSame( $code, $result->get_error_code() );
		self::assertSame( '', $result->get_error_message() );
	}

	public function test_a_radio_rejects_a_disabled_choice_and_accepts_an_enabled_one(): void {
		$field = self::field( 'radio' ) + array( 'disabled' => array( 2 ) );

		self::assertSame( 'invalid_choice', Forms::sanitize_value( $field, '2' )->get_error_code() );
		self::assertSame( '1', Forms::sanitize_value( $field, '1' ) );
	}

	public function test_a_custom_type_without_validate_is_rejected(): void {
		$field = array(
			'type'   => 'text',
			'render' => static fn (): string => '<input>',
		);

		self::assertSame( 'missing_validate', Forms::sanitize_value( $field, 'x' )->get_error_code() );
	}

	public function test_validate_receives_the_raw_submission_alone_and_its_return_is_stored_as_is(): void {
		$calls = array();
		$field = array(
			'type'     => 'select',
			'options'  => array( 'a' => 'A' ),
			'validate' => static function ( mixed ...$arguments ) use ( &$calls ): array {
				$calls[] = $arguments;
				return array( 'not a choice' );
			},
		);

		self::assertSame( array( 'not a choice' ), Forms::sanitize_value( $field, ' <b>z</b> ' ) );
		self::assertSame( array( array( ' <b>z</b> ' ) ), $calls );
	}

	public function test_validate_receives_a_cleared_multi_value_field_as_an_empty_array(): void {
		$received = null;
		$field    = array(
			'type'     => 'checkboxes',
			'options'  => array( 'a' => 'A' ),
			'validate' => static function ( mixed $raw ) use ( &$received ): mixed {
				$received = $raw;
				return $raw;
			},
		);

		self::assertSame( array(), Forms::sanitize_value( $field, '' ) );
		self::assertSame( array(), $received );
	}

	public function test_a_string_callable_validates_with_one_argument(): void {
		$field = array(
			'type'     => 'text',
			'validate' => 'wp_kses_post',
		);

		self::assertSame( '<a href="https://example.com">Docs</a>alert(1)', Forms::sanitize_value( $field, '<a href="https://example.com">Docs</a><script>alert(1)</script>' ) );
	}

	public function test_a_validate_rejection_passes_through(): void {
		$error = new \WP_Error( 'acme_too_long', 'Use at most ten characters.' );
		$field = array(
			'type'     => 'text',
			'validate' => static fn ( mixed $raw ): \WP_Error => $error,
		);

		self::assertSame( $error, Forms::sanitize_value( $field, 'x' ) );
	}

	protected static function field( string $type ): array {
		return array(
			'type'    => $type,
			'options' => self::OPTIONS,
		);
	}
}
