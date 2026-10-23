<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Core\AdminNotice;

use DeepWebSolutions\Framework\Core\AdminNotice\NoticeContext;
use DeepWebSolutions\Framework\Core\AdminNotice\NoticeScope;
use DeepWebSolutions\Framework\Shared\Exception\InvalidArgumentException;
use DeepWebSolutions\Framework\Shared\Exception\LogicException;
use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesSitesTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass( NoticeContext::class )]
final class NoticeContextTest extends TestCase {
	use CreatesSitesTrait;
	use CreatesUsersTrait;

	#[\Override]
	protected function tearDown(): void {
		\wp_set_current_user( 0 );
		while ( \is_multisite() && \ms_is_switched() ) {
			\restore_current_blog();
		}
	}

	public function test_the_current_contexts_name_the_current_blog_network_and_user(): void {
		$user_id = $this->make_user( 'acme_notices_editor', 'editor' );
		\wp_set_current_user( $user_id );

		self::assertEquals( new NoticeContext( NoticeScope::Request, 0 ), NoticeContext::current( NoticeScope::Request ) );
		self::assertEquals( new NoticeContext( NoticeScope::User, $user_id, \get_current_blog_id() ), NoticeContext::current( NoticeScope::User ) );
		self::assertEquals( new NoticeContext( NoticeScope::Site, \get_current_blog_id() ), NoticeContext::current( NoticeScope::Site ) );
		self::assertEquals( new NoticeContext( NoticeScope::Network, \get_current_network_id() ), NoticeContext::current( NoticeScope::Network ) );
	}

	public function test_a_given_user_wins_over_the_current_one(): void {
		\wp_set_current_user( $this->make_user( 'acme_notices_editor', 'editor' ) );
		$other = $this->make_user( 'acme_notices_author', 'author' );

		self::assertSame( $other, NoticeContext::current( NoticeScope::User, $other )->owner );
	}

	public function test_a_user_notice_with_nobody_logged_in_is_a_logic_error(): void {
		\wp_set_current_user( 0 );

		$this->expectException( LogicException::class );

		NoticeContext::current( NoticeScope::User );
	}

	public static function users_outside_the_user_scope(): array {
		return array(
			'request' => array( NoticeScope::Request, 1 ),
			'site'    => array( NoticeScope::Site, 1 ),
			'network' => array( NoticeScope::Network, -1 ),
			'user'    => array( NoticeScope::User, -1 ),
		);
	}

	#[DataProvider( 'users_outside_the_user_scope' )]
	public function test_a_user_outside_the_user_scope_or_a_negative_user_is_rejected( NoticeScope $scope, int $user_id ): void {
		$this->expectException( InvalidArgumentException::class );

		NoticeContext::current( $scope, $user_id );
	}

	#[Group( 'multisite' )]
	public function test_inside_a_switch_the_contexts_name_the_switched_site(): void {
		$site_id = $this->make_site( 'acme-notices' );
		$user_id = $this->make_user( 'acme_notices_editor', 'editor' );

		\switch_to_blog( $site_id );
		$contexts = array( NoticeContext::current( NoticeScope::Site ), NoticeContext::current( NoticeScope::User, $user_id ), NoticeContext::current( NoticeScope::Network ) );
		\restore_current_blog();

		self::assertEquals(
			array(
				new NoticeContext( NoticeScope::Site, $site_id ),
				new NoticeContext( NoticeScope::User, $user_id, $site_id ),
				new NoticeContext( NoticeScope::Network, \get_current_network_id() ),
			),
			$contexts
		);
	}
}
