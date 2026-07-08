<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Unit\AdminNotices\ValueObjects;

use DeepWebSolutions\Framework\Shared\ValueObject\AbstractValueObject;
use DeepWebSolutions\Framework\Shared\ValueObject\Exceptions\InvalidValueObjectException;
use DeepWebSolutions\Framework\Utilities\AdminNotices\Exceptions\InvalidAdminNoticeException;
use DeepWebSolutions\Framework\Utilities\AdminNotices\ValueObjects\AdminNotice;
use DeepWebSolutions\Framework\Utilities\AdminNotices\NoticeType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesFunction;
use PHPUnit\Framework\TestCase;

#[CoversClass( AdminNotice::class )]
#[UsesClass( AbstractValueObject::class )]
#[UsesClass( InvalidAdminNoticeException::class )]
#[UsesClass( InvalidValueObjectException::class )]
#[UsesClass( NoticeType::class )]
#[UsesFunction( 'DeepWebSolutions\Framework\Shared\Reflection\convert_to_primitives' )]
#[UsesFunction( 'DeepWebSolutions\Framework\Shared\Reflection\get_public_property_names' )]
#[UsesFunction( 'DeepWebSolutions\Framework\Utilities\AdminNotices\is_valid_notice_id' )]
final class AdminNoticeTest extends TestCase {
	public function test_constructs_with_required_arguments(): void {
		$notice = new AdminNotice( 'my-notice', 'Hello world.' );

		self::assertSame( 'my-notice', $notice->id );
		self::assertSame( 'Hello world.', $notice->message );
		self::assertSame( NoticeType::Info, $notice->type );
		self::assertTrue( $notice->dismissible );
		self::assertFalse( $notice->persistent );
		self::assertSame( 'manage_options', $notice->capability );
	}

	public function test_constructs_with_all_arguments(): void {
		$notice = new AdminNotice(
			id: 'critical',
			message: '<strong>Bad.</strong>',
			type: NoticeType::Error,
			dismissible: false,
			persistent: true,
			capability: 'activate_plugins',
		);

		self::assertSame( 'critical', $notice->id );
		self::assertSame( '<strong>Bad.</strong>', $notice->message );
		self::assertSame( NoticeType::Error, $notice->type );
		self::assertFalse( $notice->dismissible );
		self::assertTrue( $notice->persistent );
		self::assertSame( 'activate_plugins', $notice->capability );
	}

	public function test_capability_is_the_sixth_positional_argument(): void {
		$notice = new AdminNotice( 'id', 'msg', NoticeType::Warning, false, true, 'edit_posts' );

		self::assertSame( 'edit_posts', $notice->capability );
		self::assertTrue( $notice->persistent );
		self::assertFalse( $notice->dismissible );
	}

	public function test_to_array_emits_all_fields_with_type_as_backing_string(): void {
		$notice = new AdminNotice(
			id: 'id1',
			message: 'msg',
			type: NoticeType::Warning,
			dismissible: false,
			persistent: true,
			capability: 'edit_posts',
		);

		self::assertSame(
			array(
				'id'          => 'id1',
				'message'     => 'msg',
				'type'        => 'warning',
				'dismissible' => false,
				'persistent'  => true,
				'capability'  => 'edit_posts',
			),
			$notice->to_array(),
		);
	}

	public function test_from_array_reconstructs_notice(): void {
		$notice = AdminNotice::from_array(
			array(
				'id'          => 'id1',
				'message'     => 'msg',
				'type'        => 'error',
				'dismissible' => false,
				'persistent'  => true,
				'capability'  => 'manage_woocommerce',
			),
		);

		self::assertSame( 'id1', $notice->id );
		self::assertSame( 'msg', $notice->message );
		self::assertSame( NoticeType::Error, $notice->type );
		self::assertFalse( $notice->dismissible );
		self::assertTrue( $notice->persistent );
		self::assertSame( 'manage_woocommerce', $notice->capability );
	}

	public function test_to_array_from_array_round_trips_field_for_field(): void {
		$original = new AdminNotice(
			id: 'x',
			message: '',
			type: NoticeType::Success,
			dismissible: false,
			persistent: true,
			capability: 'manage_woocommerce',
		);

		$restored = AdminNotice::from_array( $original->to_array() );

		self::assertEquals( $original, $restored );
		self::assertSame( NoticeType::Success, $restored->type );
	}

	public function test_from_array_defaults_unknown_type_string_to_info(): void {
		$notice = AdminNotice::from_array(
			array(
				'id'      => 'x',
				'message' => 'm',
				'type'    => 'bogus',
			)
		);

		self::assertSame( NoticeType::Info, $notice->type );
	}

	public function test_from_array_defaults_absent_type_to_info(): void {
		$notice = AdminNotice::from_array(
			array(
				'id'      => 'x',
				'message' => 'm',
			)
		);

		self::assertSame( NoticeType::Info, $notice->type );
	}

	public function test_from_array_tolerates_non_string_type_without_error(): void {
		$notice = AdminNotice::from_array(
			array(
				'id'      => 'x',
				'message' => 'm',
				'type'    => 5,
			)
		);

		self::assertSame( NoticeType::Info, $notice->type );
	}

	public function test_from_array_absent_flags_fall_back_to_constructor_defaults(): void {
		$notice = AdminNotice::from_array(
			array(
				'id'      => 'x',
				'message' => 'm',
			)
		);

		self::assertTrue( $notice->dismissible );
		self::assertFalse( $notice->persistent );
		self::assertSame( 'manage_options', $notice->capability );
	}

	public function test_from_array_falls_back_to_defaults_for_non_bool_flags(): void {
		// A corrupt row with non-bool flags must not be coerced — the string 'false' is truthy, so a
		// (bool) cast would wrongly make the notice persistent. Non-bools fall back to the defaults.
		$notice = AdminNotice::from_array(
			array(
				'id'          => 'x',
				'message'     => 'm',
				'dismissible' => 0,
				'persistent'  => 'false',
			),
		);

		self::assertTrue( $notice->dismissible );
		self::assertFalse( $notice->persistent );
	}

	#[DataProvider( 'unstable_ids' )]
	public function test_rejects_an_unstable_id( string $unstable_id ): void {
		$this->expectException( InvalidAdminNoticeException::class );

		new AdminNotice( $unstable_id, 'message' );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function unstable_ids(): array {
		return array(
			'empty'            => array( '' ),
			'uppercase'        => array( 'My-Notice' ),
			'dot'              => array( 'notice.1' ),
			'space'            => array( 'my notice' ),
			'slash'            => array( 'plugin/notice' ),
			'trailing newline' => array( "notice\n" ),
		);
	}

	#[DataProvider( 'stable_ids' )]
	public function test_accepts_a_sanitize_key_stable_id( string $stable_id ): void {
		$notice = new AdminNotice( $stable_id, 'message' );

		self::assertSame( $stable_id, $notice->id );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function stable_ids(): array {
		return array(
			'word'            => array( 'notice' ),
			'with hyphen'     => array( 'my-notice' ),
			'with underscore' => array( 'my_notice' ),
			'with digit'      => array( 'notice2' ),
			'leading digit'   => array( '2fa-notice' ),
			'leading hyphen'  => array( '-notice' ),
		);
	}

	public function test_from_array_throws_on_an_unstable_id(): void {
		$this->expectException( InvalidAdminNoticeException::class );

		AdminNotice::from_array(
			array(
				'id'      => 'Bad.Id',
				'message' => 'm',
			)
		);
	}

	public function test_equals_is_true_for_attribute_equal_notices(): void {
		$one = new AdminNotice( 'x', 'msg', NoticeType::Warning, false, true, 'edit_posts' );
		$two = new AdminNotice( 'x', 'msg', NoticeType::Warning, false, true, 'edit_posts' );

		self::assertTrue( $one->equals( $two ) );
	}

	public function test_equals_is_false_when_any_attribute_differs(): void {
		$base = new AdminNotice( 'x', 'msg' );

		self::assertFalse( $base->equals( new AdminNotice( 'y', 'msg' ) ) );
		self::assertFalse( $base->equals( new AdminNotice( 'x', 'other' ) ) );
		self::assertFalse( $base->equals( new AdminNotice( 'x', 'msg', NoticeType::Error ) ) );
		self::assertFalse( $base->equals( new AdminNotice( 'x', 'msg', dismissible: false ) ) );
		self::assertFalse( $base->equals( new AdminNotice( 'x', 'msg', persistent: true ) ) );
		self::assertFalse( $base->equals( new AdminNotice( 'x', 'msg', capability: 'edit_posts' ) ) );
	}

	public function test_json_serialize_reduces_the_type_to_its_backing_string(): void {
		$notice = new AdminNotice( 'id1', 'msg', NoticeType::Warning, false, true, 'edit_posts' );

		self::assertSame(
			array(
				'id'          => 'id1',
				'message'     => 'msg',
				'type'        => 'warning',
				'dismissible' => false,
				'persistent'  => true,
				'capability'  => 'edit_posts',
			),
			$notice->jsonSerialize(),
		);
	}
}
