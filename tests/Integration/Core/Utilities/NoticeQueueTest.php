<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Core\Utilities;

use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use DeepWebSolutions\Framework\Utilities\NoticeQueue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass( NoticeQueue::class )]
final class NoticeQueueTest extends TestCase {
	use CreatesUsersTrait;
	use IsolatesHooksTrait;

	protected const string KEY = 'acme_notices';

	protected int $editor;

	protected int $other_user;

	protected function setUp(): void {
		$this->editor     = $this->make_user( 'acme-notice-editor', 'editor' );
		$this->other_user = $this->make_user( 'acme-notice-author', 'author' );
		$this->forget_notices();
	}

	protected function tearDown(): void {
		$this->forget_notices();
		\wp_set_current_user( 0 );
	}

	public function test_a_queued_notice_shows_once_on_the_users_next_admin_page(): void {
		self::assertFalse( \user_can( $this->editor, 'activate_plugins' ) );
		\wp_set_current_user( $this->editor );
		( new NoticeQueue( self::KEY ) )->add( 'Settings <strong>saved</strong>.', 'success' );

		$next_page = $this->render_admin_page();
		self::assertSame( 1, \substr_count( $next_page, 'Settings <strong>saved</strong>.' ) );
		self::assertStringContainsString( 'notice-success', $next_page );
		self::assertStringContainsString( 'is-dismissible', $next_page );

		self::assertStringNotContainsString( 'Settings', $this->render_admin_page() );
	}

	public function test_a_notice_shows_only_to_the_user_it_was_queued_for(): void {
		\wp_set_current_user( $this->editor );
		( new NoticeQueue( self::KEY ) )->add( 'For the editor.' );

		\wp_set_current_user( $this->other_user );
		self::assertStringNotContainsString( 'For the editor.', $this->render_admin_page() );

		\wp_set_current_user( $this->editor );
		self::assertStringContainsString( 'For the editor.', $this->render_admin_page() );
	}

	public function test_rendering_removes_only_the_notices_it_showed(): void {
		\wp_set_current_user( $this->editor );
		( new NoticeQueue( self::KEY ) )->add( 'Shown first.' );
		\add_action(
			'wp_admin_notice',
			static function (): void {
				static $queued = false;
				if ( ! $queued ) {
					$queued = true;
					( new NoticeQueue( self::KEY ) )->add( 'Queued while rendering.' );
				}
			}
		);

		$first_page = $this->render_admin_page();
		self::assertStringContainsString( 'Shown first.', $first_page );
		self::assertStringNotContainsString( 'Queued while rendering.', $first_page );

		$second_page = $this->render_admin_page();
		self::assertStringNotContainsString( 'Shown first.', $second_page );
		self::assertStringContainsString( 'Queued while rendering.', $second_page );
	}

	#[Group( 'multisite' )]
	public function test_a_notice_shows_only_on_the_site_it_was_queued_on(): void {
		self::assertTrue( \is_multisite() );
		\wp_set_current_user( $this->editor );
		( new NoticeQueue( self::KEY ) )->add( 'For the main site.' );

		\switch_to_blog( $this->make_site() );
		$other_site = $this->render_admin_page();
		\restore_current_blog();

		self::assertStringNotContainsString( 'For the main site.', $other_site );
		self::assertStringContainsString( 'For the main site.', $this->render_admin_page() );
	}

	protected function render_admin_page(): string {
		\remove_all_actions( 'all_admin_notices' );
		( new NoticeQueue( self::KEY ) )->register_hooks();

		\ob_start();
		\do_action( 'all_admin_notices' );

		return (string) \ob_get_clean();
	}

	protected function make_site(): int {
		$network = \get_network();
		self::assertInstanceOf( \WP_Network::class, $network );

		$existing = \get_site_by_path( $network->domain, $network->path . 'acme-notices/' );
		if ( $existing instanceof \WP_Site && $network->path . 'acme-notices/' === $existing->path ) {
			return (int) $existing->blog_id;
		}

		$site_id = \wp_insert_site(
			array(
				'domain' => $network->domain,
				'path'   => $network->path . 'acme-notices/',
			)
		);
		self::assertIsInt( $site_id );

		return $site_id;
	}

	protected function forget_notices(): void {
		foreach ( array( $this->editor, $this->other_user ) as $user_id ) {
			\delete_user_option( $user_id, self::KEY );
			\delete_user_option( $user_id, self::KEY, true );
			if ( \is_multisite() ) {
				\switch_to_blog( $this->make_site() );
				\delete_user_option( $user_id, self::KEY );
				\restore_current_blog();
			}
		}
	}
}
