<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Unit\AdminNotices\ValueObjects;

use DeepWebSolutions\Framework\Utilities\AdminNotices\ValueObjects\AdminNotice;
use DeepWebSolutions\Framework\Utilities\AdminNotices\NoticeType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( AdminNotice::class )]
#[UsesClass( NoticeType::class )]
final class AdminNoticeTest extends TestCase {
	public function test_constructs_with_required_arguments(): void {
		$notice = new AdminNotice( 'my-notice', 'Hello world.' );

		self::assertSame( 'my-notice', $notice->id );
		self::assertSame( 'Hello world.', $notice->message );
		self::assertSame( NoticeType::Info, $notice->type );
		self::assertTrue( $notice->is_dismissible );
		self::assertFalse( $notice->is_persistent );
		self::assertSame( 'manage_options', $notice->capability );
	}

	public function test_constructs_with_all_arguments(): void {
		$notice = new AdminNotice(
			id: 'critical',
			message: '<strong>Bad.</strong>',
			type: NoticeType::Error,
			is_dismissible: false,
			is_persistent: true,
			capability: 'activate_plugins',
		);

		self::assertSame( 'critical', $notice->id );
		self::assertSame( '<strong>Bad.</strong>', $notice->message );
		self::assertSame( NoticeType::Error, $notice->type );
		self::assertFalse( $notice->is_dismissible );
		self::assertTrue( $notice->is_persistent );
		self::assertSame( 'activate_plugins', $notice->capability );
	}

	public function test_capability_is_the_sixth_positional_argument(): void {
		$notice = new AdminNotice( 'id', 'msg', NoticeType::Warning, false, true, 'edit_posts' );

		self::assertSame( 'edit_posts', $notice->capability );
		self::assertTrue( $notice->is_persistent );
		self::assertFalse( $notice->is_dismissible );
	}

	public function test_to_array_emits_all_fields_with_type_as_backing_string(): void {
		$notice = new AdminNotice(
			id: 'id1',
			message: 'msg',
			type: NoticeType::Warning,
			is_dismissible: false,
			is_persistent: true,
			capability: 'edit_posts',
		);

		self::assertSame(
			array(
				'id'             => 'id1',
				'message'        => 'msg',
				'type'           => 'warning',
				'is_dismissible' => false,
				'is_persistent'  => true,
				'capability'     => 'edit_posts',
			),
			$notice->to_array(),
		);
	}

	public function test_from_array_reconstructs_notice(): void {
		$notice = AdminNotice::from_array(
			array(
				'id'             => 'id1',
				'message'        => 'msg',
				'type'           => 'error',
				'is_dismissible' => false,
				'is_persistent'  => true,
				'capability'     => 'manage_woocommerce',
			),
		);

		self::assertSame( 'id1', $notice->id );
		self::assertSame( 'msg', $notice->message );
		self::assertSame( NoticeType::Error, $notice->type );
		self::assertFalse( $notice->is_dismissible );
		self::assertTrue( $notice->is_persistent );
		self::assertSame( 'manage_woocommerce', $notice->capability );
	}

	public function test_to_array_from_array_round_trips_field_for_field(): void {
		$original = new AdminNotice(
			id: 'x',
			message: '',
			type: NoticeType::Success,
			is_dismissible: false,
			is_persistent: true,
			capability: 'manage_woocommerce',
		);

		$restored = AdminNotice::from_array( $original->to_array() );

		self::assertEquals( $original, $restored );
		self::assertSame( NoticeType::Success, $restored->type );
	}

	public function test_from_array_defaults_unknown_type_string_to_info(): void {
		$notice = AdminNotice::from_array( array( 'id' => 'x', 'message' => 'm', 'type' => 'bogus' ) );

		self::assertSame( NoticeType::Info, $notice->type );
	}

	public function test_from_array_defaults_absent_type_to_info(): void {
		$notice = AdminNotice::from_array( array( 'id' => 'x', 'message' => 'm' ) );

		self::assertSame( NoticeType::Info, $notice->type );
	}

	public function test_from_array_tolerates_non_string_type_without_error(): void {
		$notice = AdminNotice::from_array( array( 'id' => 'x', 'message' => 'm', 'type' => 5 ) );

		self::assertSame( NoticeType::Info, $notice->type );
	}

	public function test_from_array_absent_flags_fall_back_to_constructor_defaults(): void {
		$notice = AdminNotice::from_array( array( 'id' => 'x', 'message' => 'm' ) );

		self::assertTrue( $notice->is_dismissible );
		self::assertFalse( $notice->is_persistent );
		self::assertSame( 'manage_options', $notice->capability );
	}

	public function test_from_array_falls_back_to_defaults_for_non_bool_flags(): void {
		// A corrupt row with non-bool flags must not be coerced — the string 'false' is truthy, so a
		// (bool) cast would wrongly make the notice persistent. Non-bools fall back to the defaults.
		$notice = AdminNotice::from_array(
			array(
				'id'             => 'x',
				'message'        => 'm',
				'is_dismissible' => 0,
				'is_persistent'  => 'false',
			),
		);

		self::assertTrue( $notice->is_dismissible );
		self::assertFalse( $notice->is_persistent );
	}
}
