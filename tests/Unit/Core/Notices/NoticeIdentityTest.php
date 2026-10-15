<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Unit\Core\Notices;

use DeepWebSolutions\Framework\Notices\NoticeContext;
use DeepWebSolutions\Framework\Notices\NoticeIdentity;
use DeepWebSolutions\Framework\Notices\NoticeScope;
use DeepWebSolutions\Framework\Shared\Exception\InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( NoticeIdentity::class )]
final class NoticeIdentityTest extends TestCase {
	public static function keys(): array {
		return array(
			'request' => array( new NoticeIdentity( new NoticeContext( NoticeScope::Request, 0 ), 'saved', 1 ), 'request:0:saved' ),
			'user'    => array( new NoticeIdentity( new NoticeContext( NoticeScope::User, 7, 2 ), 'saved', 3 ), 'user:2:7:saved' ),
			'site'    => array( new NoticeIdentity( new NoticeContext( NoticeScope::Site, 2 ), 'saved', 1 ), 'site:2:saved' ),
			'network' => array( new NoticeIdentity( new NoticeContext( NoticeScope::Network, 3 ), 'saved', 12 ), 'network:3:saved' ),
		);
	}

	#[DataProvider( 'keys' )]
	public function test_the_notice_key_names_the_scope_owner_and_id( NoticeIdentity $identity, string $notice_key ): void {
		self::assertSame( $notice_key, $identity->to_notice_key() );
	}

	public function test_notices_of_other_scopes_owners_sites_or_ids_have_other_keys_and_revisions_share_one(): void {
		$keys = \array_map(
			static fn ( NoticeIdentity $identity ): string => $identity->to_notice_key(),
			array(
				new NoticeIdentity( new NoticeContext( NoticeScope::Request, 0 ), 'x', 1 ),
				new NoticeIdentity( new NoticeContext( NoticeScope::Site, 1 ), 'x', 1 ),
				new NoticeIdentity( new NoticeContext( NoticeScope::Site, 2 ), 'x', 1 ),
				new NoticeIdentity( new NoticeContext( NoticeScope::Network, 1 ), 'x', 1 ),
				new NoticeIdentity( new NoticeContext( NoticeScope::Network, 2 ), 'x', 1 ),
				new NoticeIdentity( new NoticeContext( NoticeScope::User, 1, 1 ), 'x', 1 ),
				new NoticeIdentity( new NoticeContext( NoticeScope::User, 1, 2 ), 'x', 1 ),
				new NoticeIdentity( new NoticeContext( NoticeScope::User, 2, 1 ), 'x', 1 ),
				new NoticeIdentity( new NoticeContext( NoticeScope::Site, 1 ), 'y', 1 ),
			)
		);

		self::assertSame( $keys, \array_unique( $keys ) );
		self::assertSame( $keys[1], ( new NoticeIdentity( new NoticeContext( NoticeScope::Site, 1 ), 'x', 2 ) )->to_notice_key() );
	}

	public static function invalid_identities(): array {
		return array(
			'invalid-id'    => array( 'A', 1 ),
			'id-with-colon' => array( 'a:b', 1 ),
			'revision-zero' => array( 'a', 0 ),
		);
	}

	#[DataProvider( 'invalid_identities' )]
	public function test_an_invalid_id_or_revision_is_rejected( string $id, int $revision ): void {
		$this->expectException( InvalidArgumentException::class );

		new NoticeIdentity( new NoticeContext( NoticeScope::Request, 0 ), $id, $revision );
	}
}
