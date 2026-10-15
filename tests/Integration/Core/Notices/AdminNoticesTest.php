<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Core\Notices;

use DeepWebSolutions\Framework\Notices\AdminNotices;
use DeepWebSolutions\Framework\Notices\Notice;
use DeepWebSolutions\Framework\Notices\NoticeContext;
use DeepWebSolutions\Framework\Notices\NoticeIdentity;
use DeepWebSolutions\Framework\Notices\NoticeScope;
use DeepWebSolutions\Framework\Notices\NoticeSelector;
use DeepWebSolutions\Framework\Notices\NoticeType;
use DeepWebSolutions\Framework\Notices\RecipientState;
use DeepWebSolutions\Framework\Notices\StoredNotice;
use DeepWebSolutions\Framework\Notices\Stores\RequestNoticeStore;
use DeepWebSolutions\Framework\Notices\Stores\UserMetaRecipientStates;
use DeepWebSolutions\Framework\Shared\Exception\InvalidArgumentException;
use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesSitesTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\InterceptsQueriesTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesStorageTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\RendersAdminScreensTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger as PsrAbstractLogger;

final class NoticesLogger extends PsrAbstractLogger {
	public array $records = array();

	#[\Override]
	public function log( mixed $level, string|\Stringable $message, array $context = array() ): void {
		$this->records[] = array( $level, (string) $message, $context );
	}
}

#[CoversClass( AdminNotices::class )]
#[CoversClass( NoticeSelector::class )]
final class AdminNoticesTest extends TestCase {
	use CreatesSitesTrait;
	use CreatesUsersTrait;
	use InterceptsQueriesTrait;
	use IsolatesHooksTrait;
	use IsolatesStorageTrait;
	use RendersAdminScreensTrait;

	protected int $admin;
	protected int $other_admin;
	protected int $editor;
	protected NoticesLogger $logger;

	#[\Override]
	protected function setUp(): void {
		$this->admin       = $this->make_user( 'acme_notices_admin', 'administrator' );
		$this->other_admin = $this->make_user( 'acme_notices_other_admin', 'administrator' );
		$this->editor      = $this->make_user( 'acme_notices_editor', 'editor' );
		$this->logger      = new NoticesLogger();
	}

	#[\Override]
	protected function tearDown(): void {
		while ( \is_multisite() && \ms_is_switched() ) {
			\restore_current_blog();
		}
	}

	public static function invalid_prefixes(): array {
		return array(
			'empty'            => array( '' ),
			'uppercase'        => array( 'Acme' ),
			'leading-digit'    => array( '1acme' ),
			'hyphen'           => array( 'ac-me' ),
			'trailing-newline' => array( "acme\n" ),
			'33-characters'    => array( 'a' . \str_repeat( 'b', 32 ) ),
			'notice-segment'   => array( 'acme_notice' ),
			'network-segment'  => array( 'acme_network_tools' ),
			'leading-notice'   => array( 'notice_acme' ),
			'only-network'     => array( 'network' ),
		);
	}

	#[DataProvider( 'invalid_prefixes' )]
	public function test_a_prefix_outside_the_pattern_or_with_a_reserved_segment_is_rejected( string $prefix ): void {
		$this->expectException( InvalidArgumentException::class );

		new AdminNotices( $prefix, $this->logger );
	}

	public function test_a_prefix_may_hold_the_reserved_words_inside_longer_segments_and_reach_32_characters(): void {
		foreach ( array( 'acme_notices', 'acmenetwork_x', 'a' . \str_repeat( 'b', 31 ) ) as $prefix ) {
			$notices = new AdminNotices( $prefix, $this->logger );
			self::assertSame( array(), $notices->get_notices( NoticeScope::Request ) );
		}
	}

	public static function scopes(): array {
		return array(
			'request' => array( NoticeScope::Request ),
			'user'    => array( NoticeScope::User ),
			'site'    => array( NoticeScope::Site ),
			'network' => array( NoticeScope::Network ),
		);
	}

	#[DataProvider( 'scopes' )]
	public function test_added_notices_are_listed_and_removed_in_their_scope_for_the_current_context( NoticeScope $scope ): void {
		\wp_set_current_user( $this->admin );
		$notices = $this->notices();

		$record = $notices->add( $this->notice( 'saved', $scope ) );

		self::assertEquals( NoticeContext::current( $scope ), $record->context );
		self::assertEquals( array( $record ), $notices->get_notices( $scope ) );
		$notices->remove( 'saved', $scope );
		self::assertSame( array(), $notices->get_notices( $scope ) );
	}

	public function test_a_user_notice_can_be_added_for_another_user(): void {
		\wp_set_current_user( $this->admin );
		$notices = $this->notices();

		$notices->add( $this->notice( 'saved' ), $this->editor );

		self::assertSame( array(), $notices->get_notices( NoticeScope::User ) );
		self::assertSame( array( 'saved' ), self::ids( $notices->get_notices( NoticeScope::User, $this->editor ) ) );
		$notices->remove( 'saved', NoticeScope::User, $this->editor );
		self::assertSame( array(), $notices->get_notices( NoticeScope::User, $this->editor ) );
	}

	public function test_a_request_notice_added_after_the_notices_showed_is_reported(): void {
		$reported = array();
		\add_filter( 'doing_it_wrong_trigger_error', '__return_false' );
		\add_action(
			'doing_it_wrong_run',
			static function ( string $function_name ) use ( &$reported ): void {
				$reported[] = $function_name;
			}
		);
		\wp_set_current_user( $this->admin );
		$notices = $this->notices();
		$notices->add( $this->notice( 'before', NoticeScope::Request ) );
		self::assertSame( array(), $reported );

		\do_action( 'all_admin_notices' );
		$notices->add( $this->notice( 'user' ) );
		$notices->add( $this->notice( 'after', NoticeScope::Request ) );

		self::assertSame( array( AdminNotices::class . '::add' ), $reported );
		self::assertSame( array( 'before', 'after' ), self::ids( $notices->get_notices( NoticeScope::Request ) ) );
	}

	public function test_stores_passed_to_the_constructor_replace_the_defaults_of_their_scopes(): void {
		$requests = new RequestNoticeStore();
		$states   = new UserMetaRecipientStates( "{$this->prefix}_custom" );
		$notices  = new AdminNotices( $this->prefix, $this->logger, array( 'request' => $requests ), $states );
		$notices->add( $this->notice( 'saved', NoticeScope::Request, persistent: true ) );
		$site = $notices->add( $this->notice( 'shared', NoticeScope::Site ) );

		$this->render( $this->admin, $notices );

		self::assertSame( array( 'saved' ), self::ids( $requests->all( NoticeContext::current( NoticeScope::Request ) ) ) );
		self::assertSame( RecipientState::Delivered, $states->get( self::identity( $site ), $this->admin ) );
		self::assertNull( ( new UserMetaRecipientStates( $this->prefix ) )->get( self::identity( $site ), $this->admin ) );
	}

	public function test_a_user_one_shot_shows_once_on_its_users_next_screen_and_never_to_another_user(): void {
		$this->notices()->add( $this->notice( 'saved', message: 'Saved.' ), $this->admin );

		self::assertSame( array(), self::notices_in( $this->render( $this->other_admin ) ) );
		$shown = $this->render( $this->admin );
		self::assertSame( array( $this->dom_id( 'user', 'saved' ) ), \array_keys( self::notices_in( $shown ) ) );
		self::assertStringContainsString( '<p>Saved.</p>', $shown );
		self::assertSame( array(), self::notices_in( $this->render( $this->admin ) ) );
		self::assertSame( array(), $this->notices()->get_notices( NoticeScope::User, $this->admin ) );
	}

	public function test_a_site_one_shot_shows_once_to_each_capable_user_and_records_each_delivery(): void {
		$record = $this->notices()->add( $this->notice( 'shared', NoticeScope::Site ) );
		$states = new UserMetaRecipientStates( $this->prefix );

		self::assertSame( array(), self::notices_in( $this->render( $this->editor ) ) );
		foreach ( array( $this->admin, $this->other_admin ) as $user_id ) {
			self::assertSame( array( $this->dom_id( 'site', 'shared' ) ), \array_keys( self::notices_in( $this->render( $user_id ) ) ) );
			self::assertSame( array(), self::notices_in( $this->render( $user_id ) ) );
			self::assertSame( RecipientState::Delivered, $states->get( self::identity( $record ), $user_id ) );
		}
		self::assertCount( 1, $this->notices()->get_notices( NoticeScope::Site ) );
	}

	public function test_a_delivered_state_hides_only_one_shots(): void {
		$record = $this->notices()->add( $this->notice( 'shared', NoticeScope::Site, persistent: true ) );
		( new UserMetaRecipientStates( $this->prefix ) )->record( self::identity( $record ), $this->admin, RecipientState::Delivered );

		self::assertSame( array( $this->dom_id( 'site', 'shared' ) ), \array_keys( self::notices_in( $this->render( $this->admin ) ) ) );
	}

	public static function surfaces(): array {
		return array(
			'site-admin'    => array( 'dashboard', array( 'network', 'site', 'user', 'request' ) ),
			'network-admin' => array( 'dashboard-network', array( 'network', 'request' ) ),
			'user-admin'    => array( 'dashboard-user', array( 'request' ) ),
		);
	}

	#[DataProvider( 'surfaces' )]
	public function test_each_surface_shows_its_scopes_in_order_and_consumes_only_what_it_shows( string $screen, array $shown ): void {
		\wp_set_current_user( $this->admin );
		$notices = $this->notices();
		foreach ( array( NoticeScope::Request, NoticeScope::User, NoticeScope::Site, NoticeScope::Network ) as $scope ) {
			$notices->add( $this->notice( 'x', $scope ) );
		}

		$html = $this->render( $this->admin, $notices, $screen );

		self::assertSame( \array_map( fn ( string $scope ): string => $this->dom_id( $scope, 'x' ), $shown ), \array_keys( self::notices_in( $html ) ) );
		self::assertSame( array(), $notices->get_notices( NoticeScope::Request ) );
		$next = \array_keys( self::notices_in( $this->render( $this->admin, $notices ) ) );
		self::assertSame( \array_map( fn ( string $scope ): string => $this->dom_id( $scope, 'x' ), \array_values( \array_diff( array( 'network', 'site', 'user', 'request' ), $shown ) ) ), $next );
	}

	public function test_nothing_shows_outside_the_admin_or_to_nobody(): void {
		\wp_set_current_user( $this->admin );
		$notices = $this->notices();
		$notices->add( $this->notice( 'x', NoticeScope::Request ) );
		$notices->add( $this->notice( 'x', NoticeScope::Site, capability: 'exist' ) );

		\ob_start();
		$notices->render_notices();
		self::assertSame( '', \ob_get_clean() );
		self::assertSame( '', $this->render( 0, $notices ) );
		self::assertSame( array(), $this->logger->records );
	}

	public function test_a_notice_limited_to_screens_shows_and_is_consumed_only_there(): void {
		$this->notices()->add( $this->notice( 'saved', screens: array( 'plugins' ) ), $this->admin );

		self::assertSame( array(), self::notices_in( $this->render( $this->admin, screen: 'dashboard' ) ) );
		self::assertSame( array( $this->dom_id( 'user', 'saved' ) ), \array_keys( self::notices_in( $this->render( $this->admin, screen: 'plugins' ) ) ) );
	}

	public function test_a_capability_limits_a_user_notice_too(): void {
		$this->notices()->add( $this->notice( 'saved', capability: 'manage_options' ), $this->editor );

		self::assertSame( array(), self::notices_in( $this->render( $this->editor ) ) );
		self::assertCount( 1, $this->notices()->get_notices( NoticeScope::User, $this->editor ) );
	}

	public function test_block_editor_screens_show_and_consume_nothing(): void {
		$notices = $this->notices();
		$notices->add( $this->notice( 'saved' ), $this->admin );
		$notices->add( $this->notice( 'shared', NoticeScope::Site ) );

		self::assertSame( '', $this->render( $this->admin, $notices, 'post', true ) );
		self::assertSame( array( $this->dom_id( 'site', 'shared' ), $this->dom_id( 'user', 'saved' ) ), \array_keys( self::notices_in( $this->render( $this->admin, $notices, 'post' ) ) ) );
	}

	public function test_an_expired_notice_does_not_show(): void {
		$this->notices()->add( $this->notice( 'old', NoticeScope::Site, persistent: true, expires_at: \time() - 1 ) );

		self::assertSame( array(), self::notices_in( $this->render( $this->admin ) ) );
	}

	public function test_the_visibility_filter_hides_a_notice_without_consuming_it_unless_it_returns_true(): void {
		$calls = array();
		\add_filter(
			"{$this->prefix}_notice_visible",
			static function ( mixed $visible, string $id, string $scope, int $revision ) use ( &$calls ): mixed {
				$calls[] = array( $visible, $id, $scope, $revision );
				return match ( $id ) {
					'hidden' => false,
					'truthy' => 1,
					default  => $visible,
				};
			},
			10,
			4
		);
		$notices = $this->notices();
		foreach ( array( 'hidden', 'truthy', 'shown' ) as $id ) {
			$notices->add( $this->notice( $id, revision: 3 ), $this->admin );
		}

		self::assertSame( array( $this->dom_id( 'user', 'shown' ) ), \array_keys( self::notices_in( $this->render( $this->admin, $notices ) ) ) );
		self::assertSame( array( true, 'hidden', 'user', 3 ), $calls[0] );
		self::assertSame( array( 'hidden', 'truthy' ), self::ids( $notices->get_notices( NoticeScope::User, $this->admin ) ) );
	}

	public function test_the_message_filter_replaces_the_printed_message_unless_it_returns_no_string(): void {
		$calls = array();
		\add_filter(
			"{$this->prefix}_notice_message",
			static function ( string $message, string $id, string $scope, int $revision ) use ( &$calls ): ?string {
				$calls[] = array( $message, $id, $scope, $revision );
				return 'kept' === $id ? null : 'Translated.';
			},
			10,
			4
		);
		$notices = $this->notices();
		$notices->add( $this->notice( 'changed', NoticeScope::Request, message: 'Original.', revision: 2 ) );
		$notices->add( $this->notice( 'kept', NoticeScope::Request, message: 'Kept.' ) );

		$html = $this->render( $this->admin, $notices );

		self::assertStringContainsString( '<p>Translated.</p>', $html );
		self::assertStringNotContainsString( 'Original.', $html );
		self::assertStringContainsString( '<p>Kept.</p>', $html );
		self::assertSame( array( 'Original.', 'changed', 'request', 2 ), $calls[0] );
	}

	public function test_the_markup_names_each_scopes_notice_apart_and_tickets_only_persistent_dismissible_notices(): void {
		\wp_set_current_user( $this->admin );
		$notices = $this->notices();
		$notices->add( $this->notice( 'x', NoticeScope::Request, type: NoticeType::Warning, persistent: true, revision: 4 ) );
		$notices->add( $this->notice( 'x', NoticeScope::Site, persistent: true, dismissible: false ) );
		$notices->add( $this->notice( 'once' ) );

		$shown = self::notices_in( $this->render( $this->admin, $notices ) );

		self::assertSame( array( $this->dom_id( 'site', 'x' ), $this->dom_id( 'user', 'once' ), $this->dom_id( 'request', 'x' ) ), \array_keys( $shown ) );
		$request = $shown[ $this->dom_id( 'request', 'x' ) ];
		self::assertSame( 'notice notice-warning is-dismissible', $request['class'] );
		self::assertSame( array( $this->prefix, 'request', 'x', '4' ), array( $request['data-notices'], $request['data-notice-scope'], $request['data-notice-id'], $request['data-notice-revision'] ) );
		self::assertSame( 1, \wp_verify_nonce( $request['data-notice-ticket'], "{$this->prefix}_dismiss|request:0:x@4" ) );
		self::assertSame( 'notice notice-error', $shown[ $this->dom_id( 'site', 'x' ) ]['class'] );
		self::assertArrayNotHasKey( 'data-notice-ticket', $shown[ $this->dom_id( 'site', 'x' ) ] );
		self::assertSame( 'notice notice-error is-dismissible', $shown[ $this->dom_id( 'user', 'once' ) ]['class'] );
		self::assertArrayNotHasKey( 'data-notice-ticket', $shown[ $this->dom_id( 'user', 'once' ) ] );
	}

	public function test_the_dismiss_script_prints_only_after_a_ticketed_notice_and_targets_this_plugins_notices(): void {
		\wp_set_current_user( $this->admin );
		$notices = $this->notices();
		$notices->add( $this->notice( 'once', NoticeScope::Request ) );
		$this->render( $this->admin, $notices );
		self::assertSame( '', $this->print_script( $notices ) );

		$notices->add( $this->notice( 'kept', NoticeScope::Request, persistent: true ) );
		$this->render( $this->admin, $notices );
		$script = $this->print_script( $notices );

		self::assertSame( 1, \preg_match( '/\}\s*\)\(\s*(\{.*\})\s*\);/s', $script, $matches ) );
		$settings = \json_decode( $matches[1], true );
		self::assertSame( $this->prefix, $settings['prefix'] );
		self::assertSame( \rest_url( \str_replace( '_', '-', $this->prefix ) . '/v1/notices/dismissals' ), $settings['url'] );
		self::assertSame( 1, \wp_verify_nonce( $settings['nonce'], 'wp_rest' ) );
		self::assertStringContainsString( '\/', $matches[1] );
		self::assertStringContainsString( "getAttribute( 'data-notices' ) !== settings.prefix", $script );
	}

	public function test_registered_hooks_print_the_notices_and_the_script(): void {
		$notices = $this->notices();
		$notices->register_hooks();
		$notices->add( $this->notice( 'kept', NoticeScope::Site, persistent: true ) );
		$this->view_as( $this->admin );

		\ob_start();
		\do_action( 'all_admin_notices' );
		\do_action( 'admin_footer' );
		$html = (string) \ob_get_clean();

		self::assertSame( array( $this->dom_id( 'site', 'kept' ) ), \array_keys( self::notices_in( $html ) ) );
		self::assertStringContainsString( "\"prefix\":\"{$this->prefix}\"", $html );
		self::assertSame( 10, \has_action( 'rest_api_init', array( $notices, 'register_rest_routes' ) ) );
	}

	public function test_a_network_notice_on_a_single_site_shows_like_a_site_notice_under_its_own_option_prefix(): void {
		if ( \is_multisite() ) {
			self::markTestSkipped( 'A single-site row.' );
		}

		$this->notices()->add( $this->notice( 'x', NoticeScope::Network ) );

		self::assertSame( array( $this->dom_id( 'network', 'x' ) ), \array_keys( self::notices_in( $this->render( $this->admin ) ) ) );
		self::assertIsArray( \get_option( "{$this->prefix}_network_notice_x" ) );
		self::assertFalse( \get_option( "{$this->prefix}_notice_x" ) );
	}

	public function test_a_failing_store_is_logged_and_the_other_scopes_still_show(): void {
		$notices = $this->notices();
		$notices->add( $this->notice( 'shared', NoticeScope::Site ) );
		$notices->add( $this->notice( 'saved' ), $this->admin );
		$this->fail_queries( \addslashes( $GLOBALS['wpdb']->esc_like( "{$this->prefix}_notice_" ) ) );

		self::assertSame( array( $this->dom_id( 'user', 'saved' ) ), \array_keys( self::notices_in( $this->render( $this->admin, $notices ) ) ) );
		self::assertCount( 1, $this->logger->records );
		self::assertSame( array( 'error', 'Could not select the {scope} notices: {message}', 'site' ), array( $this->logger->records[0][0], $this->logger->records[0][1], $this->logger->records[0][2]['scope'] ) );
		self::assertInstanceOf( \Throwable::class, $this->logger->records[0][2]['exception'] );
	}

	public function test_a_failed_acknowledgement_is_logged_and_the_notice_shows_again(): void {
		$notices = $this->notices();
		$notices->add( $this->notice( 'saved' ), $this->admin );
		$this->fail_queries( 'WHERE `umeta_id` =' );

		self::assertCount( 1, self::notices_in( $this->render( $this->admin, $notices ) ) );
		self::assertSame( array( 'error', 'Could not acknowledge a {scope} notice: {message}', 'user' ), array( $this->logger->records[0][0], $this->logger->records[0][1], $this->logger->records[0][2]['scope'] ) );
		self::assertCount( 1, $notices->get_notices( NoticeScope::User, $this->admin ) );
	}

	#[Group( 'multisite' )]
	public function test_a_user_notice_shows_only_on_its_site(): void {
		$site_id = $this->make_site( 'acme-notices' );
		\switch_to_blog( $site_id );
		$this->notices()->add( $this->notice( 'saved' ), $this->admin );
		\restore_current_blog();

		self::assertSame( array(), self::notices_in( $this->render( $this->admin ) ) );
		\switch_to_blog( $site_id );
		self::assertSame( array( $this->dom_id( 'user', 'saved' ) ), \array_keys( self::notices_in( $this->render( $this->admin ) ) ) );
	}

	#[Group( 'multisite' )]
	public function test_a_network_notice_shows_on_every_site_and_in_the_network_admin_from_the_main_sites_options(): void {
		$site_id = $this->make_site( 'acme-notices' );
		\add_user_to_blog( $site_id, $this->admin, 'administrator' );
		\switch_to_blog( $site_id );
		$this->notices()->add( $this->notice( 'x', NoticeScope::Network, persistent: true, capability: 'read' ) );
		self::assertSame( array( $this->dom_id( 'network', 'x' ) ), \array_keys( self::notices_in( $this->render( $this->admin ) ) ) );
		\restore_current_blog();

		self::assertSame( array( $this->dom_id( 'network', 'x' ) ), \array_keys( self::notices_in( $this->render( $this->admin ) ) ) );
		self::assertSame( array( $this->dom_id( 'network', 'x' ) ), \array_keys( self::notices_in( $this->render( $this->admin, screen: 'dashboard-network' ) ) ) );
		self::assertIsArray( \get_blog_option( \get_main_site_id(), "{$this->prefix}_network_notice_x" ) );
		self::assertFalse( \get_blog_option( $site_id, "{$this->prefix}_network_notice_x" ) );
	}

	protected function notices(): AdminNotices {
		return new AdminNotices( $this->prefix, $this->logger );
	}

	// Renders what one admin request shows the user, through a fresh instance unless the request's own is given.
	protected function render( int $user_id, ?AdminNotices $notices = null, string $screen = 'dashboard', bool $block_editor = false ): string {
		$this->view_as( $user_id, $screen, $block_editor );
		\ob_start();
		( $notices ?? $this->notices() )->render_notices();

		return (string) \ob_get_clean();
	}

	protected function print_script( AdminNotices $notices ): string {
		\ob_start();
		$notices->print_dismiss_script();

		return (string) \ob_get_clean();
	}

	protected function notice( string $id, NoticeScope $scope = NoticeScope::User, string $message = '', mixed ...$arguments ): Notice {
		$arguments['scope']        = $scope;
		$arguments['capability'] ??= NoticeScope::Site === $scope || NoticeScope::Network === $scope ? 'manage_options' : null;

		return new Notice( $id, '' === $message ? "Notice $id." : $message, ...$arguments );
	}

	protected function dom_id( string $scope, string $id ): string {
		return "{$this->prefix}-$scope-notice-$id";
	}

	protected static function identity( StoredNotice $record ): NoticeIdentity {
		return new NoticeIdentity( $record->context, $record->notice->id, $record->notice->revision );
	}

	protected static function ids( array $records ): array {
		return \array_map( static fn ( StoredNotice $record ): string => $record->notice->id, $records );
	}
}
