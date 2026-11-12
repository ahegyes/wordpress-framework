<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Core\AdminNotice;

use DeepWebSolutions\Framework\Core\AdminNotice\AdminNotices;
use DeepWebSolutions\Framework\Core\AdminNotice\NoticeScope;
use DeepWebSolutions\Framework\Core\AdminNotice\NoticeType;
use DeepWebSolutions\Framework\Core\Logging\ErrorLogLogger;
use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesSitesTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesStorageTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\RendersAdminScreensTrait;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass( AdminNotices::class )]
final class AdminNoticesBlockEditorTest extends TestCase {
	use AdminNoticeFixturesTrait;
	use CreatesSitesTrait;
	use CreatesUsersTrait;
	use IsolatesHooksTrait;
	use IsolatesStorageTrait;
	use RendersAdminScreensTrait;

	protected int $admin;
	protected int $other_admin;

	#[\Override]
	protected function setUp(): void {
		$this->admin       = $this->make_user( 'acme_notices_admin', 'administrator' );
		$this->other_admin = $this->make_user( 'acme_notices_other_admin', 'administrator' );

		// A server holding only this plugin's routes keeps every core and WooCommerce controller out of the run's memory.
		remove_all_actions( 'rest_api_init' );
		$this->notices()->register_hooks();
		$GLOBALS['wp_rest_server'] = null;
		rest_get_server();
	}

	#[\Override]
	protected function tearDown(): void {
		$GLOBALS['wp_rest_server'] = null;
	}

	public function test_the_post_editor_loads_knowing_whether_one_shots_wait_and_acknowledges_none(): void {
		$this->add_every_kind();

		self::assertTrue( $this->load_editor( $this->admin )['waiting'] );
		wp_set_current_user( $this->admin );
		self::assertSame( $this->every_one_shot(), $this->deliver()->get_data() );
		self::assertFalse( $this->load_editor( $this->admin )['waiting'] );
	}

	public function test_the_delivery_endpoint_returns_the_waiting_one_shots_once_and_they_are_acknowledged(): void {
		$this->add_every_kind();
		wp_set_current_user( $this->admin );

		$response = $this->deliver();

		self::assertSame( array( 200, $this->every_one_shot() ), array( $response->get_status(), $response->get_data() ) );
		$this->assert_only_the_others_wait();
		self::assertSame( array(), $this->deliver()->get_data() );
	}

	public function test_the_delivery_endpoint_answers_only_a_logged_in_user(): void {
		$this->notices()->add( $this->notice( 'saved' ), $this->admin );

		$response = $this->deliver();

		self::assertSame( array( 401, 'rest_forbidden' ), array( $response->get_status(), $response->get_data()['code'] ) );
		self::assertCount( 1, $this->notices()->get_notices( NoticeScope::User, $this->admin ) );
	}

	public function test_a_delivered_message_is_filtered_and_keeps_only_post_markup(): void {
		add_filter( "$this->hook_root/admin_notice/message", static fn ( string $message ): string => "$message <script>alert( 1 );</script>", 10, 3 );
		$this->notices()->add( $this->notice( 'saved', message: 'Read <a href="https://example.org/?a=1&amp;b=2">the guide</a>.' ), $this->admin );
		wp_set_current_user( $this->admin );

		self::assertSame( wp_kses_post( 'Read <a href="https://example.org/?a=1&amp;b=2">the guide</a>. <script>alert( 1 );</script>' ), $this->deliver()->get_data()[0]['content'] );
	}

	public function test_a_delivered_message_carries_its_plain_text_for_screen_readers(): void {
		$this->notices()->add( $this->notice( 'saved', message: 'Saved &amp; filed. Read <a href="https://example.org/">the &ldquo;guide&rdquo;</a>.' ), $this->admin );
		wp_set_current_user( $this->admin );

		self::assertSame( 'Saved & filed. Read the “guide”.', $this->deliver()->get_data()[0]['spoken'] );
	}

	public function test_a_delivered_message_s_paragraphs_and_list_items_are_spoken_apart(): void {
		$this->notices()->add( $this->notice( 'saved', message: '<p>Saved.</p><p>Check:</p><ul><li>Box</li><li>Weight</li></ul>' ), $this->admin );
		wp_set_current_user( $this->admin );

		self::assertSame( 'Saved. Check: Box Weight', $this->deliver()->get_data()[0]['spoken'] );
	}

	public static function other_screens(): array {
		return array(
			'site-editor'    => array( 'site-editor', true ),
			'widgets-editor' => array( 'widgets', true ),
			'classic-editor' => array( 'post', false ),
			'dashboard'      => array( 'dashboard', false ),
		);
	}

	#[DataProvider( 'other_screens' )]
	public function test_no_other_screen_loads_the_script_or_acknowledges_a_notice( string $screen, bool $block_editor ): void {
		$notices = $this->notices();
		$notices->add( $this->notice( 'saved' ), $this->admin );
		$this->view_as( $this->admin, $screen, $block_editor );

		$notices->enqueue_delivery_script();

		self::assertFalse( wp_script_is( $this->script_handle(), 'registered' ) );
		self::assertCount( 1, $notices->get_notices( NoticeScope::User, $this->admin ) );
	}

	public function test_the_script_loads_after_its_dependencies_with_the_delivery_path_and_whether_notices_wait(): void {
		$settings = $this->load_editor( $this->admin );

		self::assertTrue( wp_script_is( $this->script_handle() ) );
		self::assertSame( array( 'wp-a11y', 'wp-api-fetch', 'wp-data', 'wp-hooks', 'wp-notices' ), wp_scripts()->registered[ $this->script_handle() ]->deps );
		self::assertSame(
			array(
				'prefix'  => $this->prefix,
				'path'    => "$this->slug/v1/notices/deliveries",
				'waiting' => false,
			),
			$settings
		);
	}

	public function test_registered_hooks_load_the_script_on_the_editors_assets_and_add_the_delivery_endpoint(): void {
		$notices = $this->notices();
		$notices->register_hooks();

		self::assertSame( 10, has_action( 'enqueue_block_editor_assets', array( $notices, 'enqueue_delivery_script' ) ) );
		self::assertArrayHasKey( "/$this->slug/v1/notices/deliveries", rest_get_server()->get_routes() );
	}

	#[Group( 'multisite' )]
	public function test_a_network_one_shot_reaches_the_post_editor_of_any_site(): void {
		$site_id = $this->make_site( 'acme-notices' );
		add_user_to_blog( $site_id, $this->admin, 'administrator' );
		$this->notices()->add( $this->notice( 'x', NoticeScope::Network, capability: 'read' ) );
		switch_to_blog( $site_id );
		wp_set_current_user( $this->admin );

		self::assertSame( array( "$this->prefix-network-notice-x" ), \array_column( $this->deliver()->get_data(), 'id' ) );
		self::assertSame( array(), $this->notices()->get_notices( NoticeScope::Network ) );
	}

	protected function add_every_kind(): void {
		$notices = $this->notices();
		$notices->add( $this->notice( 'saved', message: 'Saved <b>it</b>.' ), $this->admin );
		$notices->add( $this->notice( 'pinned', type: NoticeType::Warning, dismissible: false ), $this->admin );
		$notices->add( $this->notice( 'scoped', screens: array( 'post' ) ), $this->admin );
		$notices->add( $this->notice( 'migrated', NoticeScope::Site, type: NoticeType::Success ) );
		$notices->add( $this->notice( 'news', NoticeScope::Site, per_viewer: true ) );
		$notices->add( $this->notice( 'setup', NoticeScope::Site, persistent: true ) );
		$notices->add( $this->notice( 'other', NoticeScope::User ), $this->other_admin );
	}

	protected function every_one_shot(): array {
		return array(
			$this->delivered( 'site', 'migrated', 'success', 'Notice migrated.' ),
			$this->delivered( 'site', 'news', 'error', 'Notice news.' ),
			$this->delivered( 'user', 'saved', 'error', 'Saved <b>it</b>.', spoken: 'Saved it.' ),
			$this->delivered( 'user', 'pinned', 'warning', 'Notice pinned.', false ),
		);
	}

	protected function assert_only_the_others_wait(): void {
		$notices = $this->notices();
		self::assertSame( array( 'news', 'setup' ), self::ids( $notices->get_notices( NoticeScope::Site ) ) );
		self::assertSame( array( 'scoped' ), self::ids( $notices->get_notices( NoticeScope::User, $this->admin ) ) );
		self::assertSame( array( 'other' ), self::ids( $notices->get_notices( NoticeScope::User, $this->other_admin ) ) );
		self::assertSame( 'delivered', get_user_option( "{$this->prefix}_hidden_notices", $this->admin )['site:news']['state'] ?? null );
	}

	protected function delivered( string $scope, string $id, string $status, string $content, bool $dismissible = true, ?string $spoken = null ): array {
		return array(
			'id'            => "$this->prefix-$scope-notice-$id",
			'status'        => $status,
			'content'       => $content,
			'spoken'        => $spoken ?? $content,
			'isDismissible' => $dismissible,
		);
	}

	// A fresh instance stands for the next request.
	protected function load_editor( int $user_id ): array {
		$this->remove_the_script();
		$this->view_as( $user_id, 'post', true );
		$this->notices()->enqueue_delivery_script();
		self::assertSame( 1, \preg_match( '/\}\s*\)\(\s*(\{.*\})\s*\);/s', \implode( "\n", wp_scripts()->get_data( $this->script_handle(), 'after' ) ), $matches ) );

		return \json_decode( $matches[1], true );
	}

	protected function deliver(): \WP_REST_Response {
		return rest_do_request( new \WP_REST_Request( 'POST', "/$this->slug/v1/notices/deliveries" ) );
	}

	protected function notices(): AdminNotices {
		return new AdminNotices( $this->identity, new ErrorLogLogger( $this->identity ) );
	}

	protected function script_handle(): string {
		return "$this->prefix-notice-delivery";
	}

	#[After]
	protected function remove_the_script(): void {
		wp_dequeue_script( $this->script_handle() );
		wp_deregister_script( $this->script_handle() );
	}
}
