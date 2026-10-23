<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Unit\Core\AdminNotice;

use DeepWebSolutions\Framework\Core\AdminNotice\NoticeContext;
use DeepWebSolutions\Framework\Core\AdminNotice\NoticeScope;
use DeepWebSolutions\Framework\Shared\Exception\InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( NoticeContext::class )]
final class NoticeContextTest extends TestCase {
	public static function fitting_contexts(): array {
		return array(
			'request' => array( NoticeScope::Request, 0, 0 ),
			'user'    => array( NoticeScope::User, 7, 2 ),
			'site'    => array( NoticeScope::Site, 1, 0 ),
			'network' => array( NoticeScope::Network, 1, 0 ),
		);
	}

	#[DataProvider( 'fitting_contexts' )]
	public function test_an_owner_and_site_that_fit_the_scope_are_kept( NoticeScope $scope, int $owner, int $site_id ): void {
		$context = new NoticeContext( $scope, $owner, $site_id );

		self::assertSame( array( $scope, $owner, $site_id ), array( $context->scope, $context->owner, $context->site_id ) );
	}

	public static function unfitting_contexts(): array {
		return array(
			'request-with-an-owner'  => array( NoticeScope::Request, 1, 0 ),
			'request-with-a-site'    => array( NoticeScope::Request, 0, 1 ),
			'user-without-a-user'    => array( NoticeScope::User, 0, 1 ),
			'user-without-a-site'    => array( NoticeScope::User, 1, 0 ),
			'user-with-negative-ids' => array( NoticeScope::User, -1, -1 ),
			'site-without-a-blog'    => array( NoticeScope::Site, 0, 0 ),
			'site-with-a-site'       => array( NoticeScope::Site, 1, 1 ),
			'network-without-one'    => array( NoticeScope::Network, 0, 0 ),
			'network-with-a-site'    => array( NoticeScope::Network, 1, 1 ),
		);
	}

	#[DataProvider( 'unfitting_contexts' )]
	public function test_an_owner_or_site_that_does_not_fit_the_scope_is_rejected( NoticeScope $scope, int $owner, int $site_id ): void {
		$this->expectException( InvalidArgumentException::class );

		new NoticeContext( $scope, $owner, $site_id );
	}
}
