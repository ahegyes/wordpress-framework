<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Unit\Core\AdminNotice;

use DeepWebSolutions\Framework\Core\AdminNotice\NoticeContext;
use DeepWebSolutions\Framework\Core\AdminNotice\NoticeIdentity;
use DeepWebSolutions\Framework\Core\AdminNotice\NoticeScope;
use DeepWebSolutions\Framework\Shared\Exception\InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( NoticeIdentity::class )]
final class NoticeIdentityTest extends TestCase {
	public static function keys(): array {
		return array(
			'request' => array( new NoticeIdentity( new NoticeContext( NoticeScope::Request, 0 ), 'saved' ), 'request:0:saved' ),
			'user'    => array( new NoticeIdentity( new NoticeContext( NoticeScope::User, 7, 2 ), 'saved' ), 'user:2:7:saved' ),
			'site'    => array( new NoticeIdentity( new NoticeContext( NoticeScope::Site, 2 ), 'saved' ), 'site:2:saved' ),
			'network' => array( new NoticeIdentity( new NoticeContext( NoticeScope::Network, 3 ), 'saved' ), 'network:3:saved' ),
		);
	}

	#[DataProvider( 'keys' )]
	public function test_the_key_names_the_scope_owner_and_id( NoticeIdentity $identity, string $key ): void {
		self::assertSame( $key, $identity->to_key() );
	}

	public function test_notices_of_other_scopes_owners_sites_or_ids_have_other_keys(): void {
		$keys = \array_map(
			static fn ( NoticeIdentity $identity ): string => $identity->to_key(),
			array(
				new NoticeIdentity( new NoticeContext( NoticeScope::Request, 0 ), 'x' ),
				new NoticeIdentity( new NoticeContext( NoticeScope::Site, 1 ), 'x' ),
				new NoticeIdentity( new NoticeContext( NoticeScope::Site, 2 ), 'x' ),
				new NoticeIdentity( new NoticeContext( NoticeScope::Network, 1 ), 'x' ),
				new NoticeIdentity( new NoticeContext( NoticeScope::Network, 2 ), 'x' ),
				new NoticeIdentity( new NoticeContext( NoticeScope::User, 1, 1 ), 'x' ),
				new NoticeIdentity( new NoticeContext( NoticeScope::User, 1, 2 ), 'x' ),
				new NoticeIdentity( new NoticeContext( NoticeScope::User, 2, 1 ), 'x' ),
				new NoticeIdentity( new NoticeContext( NoticeScope::Site, 1 ), 'y' ),
			)
		);

		self::assertSame( $keys, \array_unique( $keys ) );
	}

	public static function invalid_identities(): array {
		return array(
			'invalid-id'    => array( 'A' ),
			'id-with-colon' => array( 'a:b' ),
		);
	}

	#[DataProvider( 'invalid_identities' )]
	public function test_an_invalid_id_is_rejected( string $id ): void {
		$this->expectException( InvalidArgumentException::class );

		new NoticeIdentity( new NoticeContext( NoticeScope::Request, 0 ), $id );
	}
}
