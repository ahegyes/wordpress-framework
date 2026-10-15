<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Unit\Core\Notices;

use DeepWebSolutions\Framework\Notices\Notice;
use DeepWebSolutions\Framework\Notices\NoticeScope;
use DeepWebSolutions\Framework\Notices\NoticeType;
use DeepWebSolutions\Framework\Shared\Exception\InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( Notice::class )]
final class NoticeTest extends TestCase {
	public function test_the_defaults_are_a_dismissible_one_shot_user_error_for_every_screen(): void {
		$notice = new Notice( 'saved', '<p>Saved.</p>' );

		self::assertSame(
			array(
				'v'           => 1,
				'id'          => 'saved',
				'message'     => '<p>Saved.</p>',
				'type'        => 'error',
				'scope'       => 'user',
				'persistent'  => false,
				'dismissible' => true,
				'capability'  => null,
				'screens'     => array(),
				'expires_at'  => null,
			),
			$notice->to_array()
		);
	}

	public static function valid_notices(): array {
		return array(
			'every-field-set'      => array( new Notice( 'acme-sync_failed', "Can't \\ \"sync\"", NoticeType::Warning, NoticeScope::Network, true, false, 'manage_network_options', array( 'plugins', 'dashboard-network' ), 1_900_000_000 ) ),
			'request-scope'        => array( new Notice( 'a', 'Hi', NoticeType::Info, NoticeScope::Request ) ),
			'site-scope'           => array( new Notice( 'a', 'Hi', NoticeType::Success, NoticeScope::Site, capability: 'manage_options' ) ),
			'user-with-capability' => array( new Notice( 'a', 'Hi', capability: 'edit_posts' ) ),
			'64-character-id'      => array( new Notice( 'a' . \str_repeat( '0', 63 ), 'Hi' ) ),
			'expired-already'      => array( new Notice( 'a', 'Hi', expires_at: 1 ) ),
			'empty-message'        => array( new Notice( 'a', '' ) ),
		);
	}

	#[DataProvider( 'valid_notices' )]
	public function test_a_notice_survives_its_row( Notice $notice ): void {
		self::assertEquals( $notice, Notice::from_array( $notice->to_array() ) );
	}

	public static function invalid_notices(): array {
		return array(
			'empty-id'                   => array( static fn (): Notice => new Notice( '', 'Hi' ) ),
			'id-starting-with-a-digit'   => array( static fn (): Notice => new Notice( '1a', 'Hi' ) ),
			'id-starting-with-a-hyphen'  => array( static fn (): Notice => new Notice( '-a', 'Hi' ) ),
			'uppercase-id'               => array( static fn (): Notice => new Notice( 'Saved', 'Hi' ) ),
			'id-with-a-colon'            => array( static fn (): Notice => new Notice( 'a:b', 'Hi' ) ),
			'id-with-an-at-sign'         => array( static fn (): Notice => new Notice( 'a@b', 'Hi' ) ),
			'id-with-a-trailing-newline' => array( static fn (): Notice => new Notice( "a\n", 'Hi' ) ),
			'65-character-id'            => array( static fn (): Notice => new Notice( 'a' . \str_repeat( '0', 64 ), 'Hi' ) ),
			'empty-capability'           => array( static fn (): Notice => new Notice( 'a', 'Hi', capability: '' ) ),
			'site-without-capability'    => array( static fn (): Notice => new Notice( 'a', 'Hi', scope: NoticeScope::Site ) ),
			'network-without-capability' => array( static fn (): Notice => new Notice( 'a', 'Hi', scope: NoticeScope::Network ) ),
			'screens-not-a-list'         => array( static fn (): Notice => new Notice( 'a', 'Hi', screens: array( 'main' => 'plugins' ) ) ),
			'screen-not-a-string'        => array( static fn (): Notice => new Notice( 'a', 'Hi', screens: array( 'plugins', 7 ) ) ),
			'empty-screen'               => array( static fn (): Notice => new Notice( 'a', 'Hi', screens: array( '' ) ) ),
			'expiry-zero'                => array( static fn (): Notice => new Notice( 'a', 'Hi', expires_at: 0 ) ),
			'expiry-negative'            => array( static fn (): Notice => new Notice( 'a', 'Hi', expires_at: -1 ) ),
		);
	}

	#[DataProvider( 'invalid_notices' )]
	public function test_an_invalid_notice_is_rejected( \Closure $make ): void {
		$this->expectException( InvalidArgumentException::class );

		$make();
	}

	public function test_absent_optional_fields_take_their_defaults_and_unknown_keys_are_ignored(): void {
		$notice = Notice::from_array(
			array(
				'v'       => 1,
				'id'      => 'saved',
				'message' => 'Saved.',
				'gen'     => 'abc',
			)
		);

		self::assertEquals( new Notice( 'saved', 'Saved.' ), $notice );
	}

	public static function rejected_rows(): array {
		$row = ( new Notice( 'a', 'Hi', scope: NoticeScope::Site, capability: 'manage_options' ) )->to_array();

		return array(
			'version-2'                   => array( array( 'v' => 2 ) + $row ),
			'version-as-a-string'         => array( array( 'v' => '1' ) + $row ),
			'no-version'                  => array( \array_diff_key( $row, array( 'v' => true ) ) ),
			'no-id'                       => array( \array_diff_key( $row, array( 'id' => true ) ) ),
			'no-message'                  => array( \array_diff_key( $row, array( 'message' => true ) ) ),
			'invalid-id'                  => array( array( 'id' => 'A' ) + $row ),
			'message-not-a-string'        => array( array( 'message' => 7 ) + $row ),
			'unknown-type'                => array( array( 'type' => 'notice' ) + $row ),
			'type-not-a-string'           => array( array( 'type' => 1 ) + $row ),
			'unknown-scope'               => array( array( 'scope' => 'blog' ) + $row ),
			'scope-null'                  => array( array( 'scope' => null ) + $row ),
			'persistent-as-an-integer'    => array( array( 'persistent' => 1 ) + $row ),
			'dismissible-as-a-string'     => array( array( 'dismissible' => '' ) + $row ),
			'site-capability-null'        => array( array( 'capability' => null ) + $row ),
			'site-capability-absent'      => array( \array_diff_key( $row, array( 'capability' => true ) ) ),
			'empty-capability'            => array( array( 'capability' => '' ) + $row ),
			'capability-not-a-string'     => array( array( 'capability' => array( 'manage_options' ) ) + $row ),
			'screens-as-a-string'         => array( array( 'screens' => 'plugins' ) + $row ),
			'screens-null'                => array( array( 'screens' => null ) + $row ),
			'screens-not-a-list'          => array( array( 'screens' => array( 1 => 'plugins' ) ) + $row ),
			'screen-not-a-string'         => array( array( 'screens' => array( 'plugins', 7 ) ) + $row ),
			'expiry-zero'                 => array( array( 'expires_at' => 0 ) + $row ),
			'expiry-as-a-string'          => array( array( 'expires_at' => '1900000000' ) + $row ),
			'expiry-as-a-float'           => array( array( 'expires_at' => 1.9e9 ) + $row ),
			'persistence-null'            => array( array( 'persistent' => null ) + $row ),
			'dismissibility-as-an-object' => array( array( 'dismissible' => new \stdClass() ) + $row ),
		);
	}

	#[DataProvider( 'rejected_rows' )]
	public function test_a_row_with_another_version_or_an_invalid_field_is_rejected( array $row ): void {
		self::assertNull( Notice::from_array( $row ) );
	}
}
