<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Core\Notices;

use DeepWebSolutions\Framework\Notices\AdminNotices;
use DeepWebSolutions\Framework\Notices\Notice;
use DeepWebSolutions\Framework\Notices\NoticeContext;
use DeepWebSolutions\Framework\Notices\NoticeIdentity;
use DeepWebSolutions\Framework\Notices\NoticeScope;
use DeepWebSolutions\Framework\Notices\NoticeType;
use DeepWebSolutions\Framework\Notices\StoredNotice;
use DeepWebSolutions\Framework\Notices\Stores\UserMetaRecipientStates;
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

final class DismissalLogger extends PsrAbstractLogger {
	public array $records = array();

	#[\Override]
	public function log( mixed $level, string|\Stringable $message, array $context = array() ): void {
		$this->records[] = array( $level, (string) $message, $context );
	}
}

#[CoversClass( AdminNotices::class )]
final class AdminNoticesDismissalTest extends TestCase {
	use CreatesSitesTrait;
	use CreatesUsersTrait;
	use InterceptsQueriesTrait;
	use IsolatesHooksTrait;
	use IsolatesStorageTrait;
	use RendersAdminScreensTrait;

	protected int $admin;
	protected int $other_admin;
	protected DismissalLogger $logger;
	protected AdminNotices $notices;
	protected array $dismissed     = array();
	protected array $saved_request = array();

	#[\Override]
	protected function setUp(): void {
		$this->admin       = $this->make_user( 'acme_notices_admin', 'administrator' );
		$this->other_admin = $this->make_user( 'acme_notices_other_admin', 'administrator' );
		$this->logger      = new DismissalLogger();
		$this->notices     = new AdminNotices( $this->prefix, $this->logger );
		\add_action(
			"{$this->prefix}_notice_dismissed",
			function ( mixed ...$arguments ): void {
				$this->dismissed[] = $arguments;
			},
			10,
			4
		);

		$this->saved_request = array( $_SERVER, $_POST, $_COOKIE ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Saves the request globals the HTTP-path test replaces.

		// A server holding only this route keeps every core and WooCommerce controller out of the run's memory.
		\remove_all_actions( 'rest_api_init' );
		$this->notices->register_hooks();
		$GLOBALS['wp_rest_server'] = null;
		\rest_get_server();
	}

	#[\Override]
	protected function tearDown(): void {
		list( $_SERVER, $_POST, $_COOKIE ) = $this->saved_request;
		$GLOBALS['wp_rest_server']         = null;
		$GLOBALS['wp_rest_auth_cookie']    = null;
		while ( \is_multisite() && \ms_is_switched() ) {
			\restore_current_blog();
		}
	}

	public function test_a_dismissed_persistent_user_notice_stays_stored_and_hidden_when_added_again(): void {
		$this->notices->add( $this->notice( 'kept', NoticeScope::User ), $this->admin );

		$response = $this->dismiss( $this->shown( $this->admin, 'user', 'kept' ) );

		self::assertSame( array( 200, array( 'changed' => true ) ), array( $response->get_status(), $response->get_data() ) );
		self::assertSame( array(), $this->render( $this->admin ) );
		self::assertCount( 1, $this->notices->get_notices( NoticeScope::User, $this->admin ) );
		$this->notices->add( $this->notice( 'kept', NoticeScope::User ), $this->admin );
		self::assertSame( array(), $this->render( $this->admin ) );
	}

	public function test_a_shared_site_notice_dismissed_by_one_capable_user_is_deleted_for_everyone(): void {
		$this->notices->add( $this->notice( 'setup', NoticeScope::Site ) );

		self::assertSame( array( 'changed' => true ), $this->dismiss( $this->shown( $this->admin, 'site', 'setup' ) )->get_data() );

		self::assertSame( array(), $this->render( $this->other_admin ) );
		self::assertSame( array(), $this->notices->get_notices( NoticeScope::Site ) );
		self::assertNull( ( new UserMetaRecipientStates( $this->prefix ) )->get( new NoticeIdentity( NoticeContext::current( NoticeScope::Site ), 'setup' ), $this->admin ) );
		$this->notices->add( $this->notice( 'setup', NoticeScope::Site ) );
		self::assertSame( array( $this->dom_id( 'site', 'setup' ) ), \array_keys( $this->render( $this->admin ) ) );
	}

	public function test_a_shared_dismissal_leaves_a_notice_saved_again_after_it_was_shown(): void {
		$this->notices->add( $this->notice( 'setup', NoticeScope::Site ) );
		$shown = $this->shown( $this->admin, 'site', 'setup' );
		$this->notices->add( $this->notice( 'setup', NoticeScope::Site, type: NoticeType::Warning ) );

		self::assertSame( 404, $this->dismiss( $shown )->get_status() );

		self::assertSame( array( NoticeType::Warning ), \array_map( static fn ( StoredNotice $record ): NoticeType => $record->notice->type, $this->notices->get_notices( NoticeScope::Site ) ) );
		self::assertSame( array(), $this->dismissed );
	}

	public function test_a_per_viewer_site_notice_dismissed_by_one_user_still_shows_to_another(): void {
		$this->notices->add( $this->notice( 'news', NoticeScope::Site, per_viewer: true ) );

		self::assertSame( 200, $this->dismiss( $this->shown( $this->admin, 'site', 'news' ) )->get_status() );

		self::assertSame( array(), $this->render( $this->admin ) );
		self::assertSame( array( $this->dom_id( 'site', 'news' ) ), \array_keys( $this->render( $this->other_admin ) ) );
	}

	public function test_dismissing_a_request_notice_records_only_the_callers_state_and_leaves_a_site_notice_of_the_same_id(): void {
		$this->notices->add( $this->notice( 'x', NoticeScope::Request ) );
		$this->notices->add( $this->notice( 'x', NoticeScope::Site ) );

		self::assertSame( 200, $this->dismiss( $this->shown( $this->admin, 'request', 'x' ) )->get_status() );

		self::assertSame( array( $this->dom_id( 'site', 'x' ) ), \array_keys( $this->render( $this->admin ) ) );
		self::assertSame( array( $this->dom_id( 'site', 'x' ), $this->dom_id( 'request', 'x' ) ), \array_keys( $this->render( $this->other_admin ) ) );
	}

	public function test_the_dismissal_action_fires_once_per_dismissal_that_takes_effect_with_the_full_identity(): void {
		$this->notices->add( $this->notice( 'setup', NoticeScope::Site ) );
		$this->notices->add( $this->notice( 'news', NoticeScope::Site, per_viewer: true ) );
		$this->notices->add( $this->notice( 'mine', NoticeScope::User ), $this->admin );
		$setup = $this->shown( $this->admin, 'site', 'setup' );
		$news  = $this->shown( $this->admin, 'site', 'news' );
		$mine  = $this->shown( $this->admin, 'user', 'mine' );

		$this->dismiss( $setup );
		$gone = $this->dismiss( $setup );
		$this->dismiss( $news );
		$again = $this->dismiss( $news );
		$this->dismiss( $mine );

		self::assertSame( 404, $gone->get_status() );
		self::assertSame( array( 200, array( 'changed' => false ) ), array( $again->get_status(), $again->get_data() ) );
		self::assertSame(
			array(
				array( 'setup', 'site', $this->admin, \get_current_blog_id() ),
				array( 'news', 'site', $this->admin, \get_current_blog_id() ),
				array( 'mine', 'user', $this->admin, $this->admin ),
			),
			$this->dismissed
		);
	}

	public static function refusals(): array {
		return array(
			'missing-nonce'       => array( static fn ( self $test ): array => array( array( 'nonce' => null ) + $test->shown( $test->admin, 'site', 'x' ), 400 ) ),
			'another-notice'      => array(
				static function ( self $test ): array {
					$test->notices->add( $test->notice( 'other', NoticeScope::Site ) );
					return array( array( 'nonce' => $test->shown( $test->admin, 'site', 'other' )['nonce'] ) + $test->shown( $test->admin, 'site', 'x' ), 403 );
				},
			),
			'another-users-nonce' => array(
				static function ( self $test ): array {
					$shown = $test->shown( $test->other_admin, 'site', 'x' );
					\wp_set_current_user( $test->admin );
					return array( $shown, 403 );
				},
			),
			'logged-out'          => array(
				static function ( self $test ): array {
					$shown = $test->shown( $test->admin, 'site', 'x' );
					\wp_set_current_user( 0 );
					return array( $shown, 401 );
				},
			),
			'removed'             => array(
				static function ( self $test ): array {
					$shown = $test->shown( $test->admin, 'site', 'x' );
					$test->notices->remove( 'x', NoticeScope::Site );
					return array( $shown, 404 );
				},
			),
			'missing-generation'  => array( static fn ( self $test ): array => array( array( 'generation' => null ) + $test->shown( $test->admin, 'site', 'x' ), 404 ) ),
			'lacking-capability'  => array(
				static function ( self $test ): array {
					$shown = $test->shown( $test->admin, 'site', 'x' );
					return array( array( 'generation' => $test->notices->add( $test->notice( 'x', NoticeScope::Site, capability: 'acme_never_granted' ) )->generation ) + $shown, 403 );
				},
			),
			'not-dismissible'     => array(
				static function ( self $test ): array {
					$shown = $test->shown( $test->admin, 'site', 'x' );
					return array( array( 'generation' => $test->notices->add( $test->notice( 'x', NoticeScope::Site, dismissible: false ) )->generation ) + $shown, 403 );
				},
			),
			'one-shot'            => array(
				static function ( self $test ): array {
					$shown = $test->shown( $test->admin, 'site', 'x' );
					return array( array( 'generation' => $test->notices->add( $test->notice( 'x', NoticeScope::Site, persistent: false ) )->generation ) + $shown, 403 );
				},
			),
		);
	}

	#[DataProvider( 'refusals' )]
	public function test_a_dismissal_without_a_matching_nonce_audience_or_record_changes_nothing( \Closure $arrange ): void {
		$this->notices->add( $this->notice( 'x', NoticeScope::Site ) );
		\wp_set_current_user( $this->admin );
		list( $params, $status ) = $arrange( $this );
		$stored                  = $this->notices->get_notices( NoticeScope::Site );

		self::assertSame( $status, $this->dismiss( $params )->get_status() );

		self::assertEquals( $stored, $this->notices->get_notices( NoticeScope::Site ) );
		self::assertSame( array(), $this->dismissed );
	}

	public static function invalid_parameters(): array {
		return array(
			'unknown-scope'       => array( array( 'scope' => 'galaxy' ) ),
			'invalid-id'          => array( array( 'id' => 'Bad' ) ),
			'trailing-newline'    => array( array( 'id' => "x\n" ) ),
			'generation-not-text' => array( array( 'generation' => array( 1 ) ) ),
		);
	}

	#[DataProvider( 'invalid_parameters' )]
	public function test_invalid_parameters_are_rejected( array $params ): void {
		\wp_set_current_user( $this->admin );
		$valid = array(
			'scope' => 'site',
			'id'    => 'x',
			'nonce' => 'nonce',
		);

		self::assertSame( 400, $this->dismiss( $params + $valid )->get_status() );
	}

	public static function unchecked_parameters(): array {
		$valid = array(
			'scope' => 'site',
			'id'    => 'x',
			'nonce' => 'nonce',
		);

		return array(
			'unknown-scope'       => array( array( 'scope' => 'galaxy' ) + $valid ),
			'scope-not-text'      => array( array( 'scope' => 1 ) + $valid ),
			'id-not-text'         => array( array( 'id' => 1 ) + $valid ),
			'trailing-newline'    => array( array( 'id' => "x\n" ) + $valid ),
			'missing-nonce'       => array( \array_diff_key( $valid, array( 'nonce' => true ) ) ),
			'generation-not-text' => array( array( 'generation' => 1 ) + $valid ),
		);
	}

	#[DataProvider( 'unchecked_parameters' )]
	public function test_the_callback_rejects_invalid_parameters_without_the_routes_schema( array $params ): void {
		\wp_set_current_user( $this->admin );
		$request = new \WP_REST_Request( 'POST' );
		$request->set_body_params( $params );

		$response = $this->notices->rest_dismiss( $request );

		self::assertInstanceOf( \WP_Error::class, $response );
		self::assertSame( array( 'rest_invalid_param', array( 'status' => 400 ) ), array( $response->get_error_code(), $response->get_error_data() ) );
	}

	public static function failing_stores(): array {
		return array(
			'per-viewer-state' => array( true, static fn () => \add_filter( 'add_user_metadata', '__return_false' ) ),
			'shared-notice'    => array( false, static fn ( self $test ) => $test->fail_queries( 'DELETE FROM' ) ),
		);
	}

	#[DataProvider( 'failing_stores' )]
	public function test_a_failing_store_answers_500_and_is_logged( bool $per_viewer, \Closure $fail ): void {
		$this->notices->add( $this->notice( 'x', NoticeScope::Site, per_viewer: $per_viewer ) );
		$shown = $this->shown( $this->admin, 'site', 'x' );
		$fail( $this );

		self::assertSame( 500, $this->dismiss( $shown )->get_status() );

		self::assertSame( array( 'error', 'Could not dismiss a {scope} notice: {message}', 'site' ), array( $this->logger->records[0][0], $this->logger->records[0][1], $this->logger->records[0][2]['scope'] ) );
		self::assertInstanceOf( \Throwable::class, $this->logger->records[0][2]['exception'] );
		self::assertSame( array(), $this->dismissed );
	}

	public function test_the_http_path_with_cookie_authentication_needs_the_rest_nonce(): void {
		$this->notices->add( $this->notice( 'setup', NoticeScope::Site ) );
		$_SERVER['REQUEST_METHOD']    = 'GET';
		$expiration                   = \time() + \DAY_IN_SECONDS;
		$token                        = \WP_Session_Tokens::get_instance( $this->admin )->create( $expiration );
		$_COOKIE[ \LOGGED_IN_COOKIE ] = \wp_generate_auth_cookie( $this->admin, $expiration, 'logged_in', $token );
		$params                       = $this->shown( $this->admin, 'site', 'setup' );
		$rest_nonce                   = \wp_create_nonce( 'wp_rest' );

		self::assertSame( 'rest_forbidden', $this->serve( $params, null )['code'] );
		self::assertCount( 1, $this->notices->get_notices( NoticeScope::Site ) );
		self::assertSame( 'rest_cookie_invalid_nonce', $this->serve( $params, 'wrong' )['code'] );
		self::assertSame( array( 'changed' => true ), $this->serve( $params, $rest_nonce ) );
		self::assertSame( array(), $this->notices->get_notices( NoticeScope::Site ) );
	}

	#[Group( 'multisite' )]
	public function test_a_shared_network_notice_dismissed_on_one_site_is_deleted_from_the_main_sites_options(): void {
		$site_id = $this->make_site( 'acme-notices' );
		\add_user_to_blog( $site_id, $this->admin, 'administrator' );
		$this->notices->add( $this->notice( 'x', NoticeScope::Network, capability: 'read' ) );
		\switch_to_blog( $site_id );

		self::assertSame( 200, $this->dismiss( $this->shown( $this->admin, 'network', 'x' ) )->get_status() );

		\restore_current_blog();
		self::assertSame( array(), $this->render( $this->admin, 'dashboard-network' ) );
		self::assertFalse( \get_blog_option( \get_main_site_id(), "{$this->prefix}_network_notice_x" ) );
		self::assertSame( array(), \get_user_meta( $this->admin, "{$this->prefix}_network_notice_states", false ) );
	}

	#[Group( 'multisite' )]
	public function test_a_per_viewer_network_notice_dismissed_on_one_site_stays_hidden_on_every_site_and_in_the_network_admin(): void {
		$site_id = $this->make_site( 'acme-notices' );
		\add_user_to_blog( $site_id, $this->admin, 'administrator' );
		$this->notices->add( $this->notice( 'x', NoticeScope::Network, capability: 'read', per_viewer: true ) );
		\switch_to_blog( $site_id );

		self::assertSame( 200, $this->dismiss( $this->shown( $this->admin, 'network', 'x' ) )->get_status() );

		self::assertSame( array(), $this->render( $this->admin ) );
		\restore_current_blog();
		self::assertSame( array(), $this->render( $this->admin ) );
		self::assertSame( array(), $this->render( $this->admin, 'dashboard-network' ) );
		self::assertCount( 1, \get_user_meta( $this->admin, "{$this->prefix}_network_notice_states", false ) );
	}

	protected function notice( string $id, NoticeScope $scope, mixed ...$arguments ): Notice {
		$arguments['scope']        = $scope;
		$arguments['persistent'] ??= true;
		$arguments['capability'] ??= NoticeScope::Site === $scope || NoticeScope::Network === $scope ? 'manage_options' : null;

		return new Notice( $id, "Notice $id.", ...$arguments );
	}

	// What the dismiss script posts for the notice as the user is shown it.
	protected function shown( int $user_id, string $scope, string $id ): array {
		$attributes = $this->render( $user_id )[ $this->dom_id( $scope, $id ) ];

		return array(
			'scope'      => $attributes['data-notice-scope'],
			'id'         => $attributes['data-notice-id'],
			'nonce'      => $attributes['data-notice-nonce'],
			'generation' => $attributes['data-notice-generation'] ?? null,
		);
	}

	protected function render( int $user_id, string $screen = 'dashboard' ): array {
		$this->view_as( $user_id, $screen );
		\ob_start();
		$this->notices->render_notices();

		return self::notices_in( (string) \ob_get_clean() );
	}

	protected function dismiss( array $params ): \WP_REST_Response {
		$request = new \WP_REST_Request( 'POST', '/' . \str_replace( '_', '-', $this->prefix ) . '/v1/notices/dismissals' );
		$request->set_body_params( \array_filter( $params, static fn ( mixed $value ): bool => null !== $value ) );

		return \rest_do_request( $request );
	}

	// The REST nonce is checked only when the login cookie, not the test, sets the user.
	protected function serve( array $params, ?string $rest_nonce ): array {
		$GLOBALS['current_screen']      = null;
		$GLOBALS['current_user']        = null;
		$GLOBALS['wp_rest_auth_cookie'] = null;
		\wp_get_current_user();
		$_SERVER['REQUEST_METHOD'] = 'POST';
		unset( $_SERVER['HTTP_X_WP_NONCE'] );
		if ( null !== $rest_nonce ) {
			$_SERVER['HTTP_X_WP_NONCE'] = $rest_nonce;
		}
		$_POST = $params;

		\ob_start();
		\rest_get_server()->serve_request( '/' . \str_replace( '_', '-', $this->prefix ) . '/v1/notices/dismissals' );

		return \json_decode( (string) \ob_get_clean(), true );
	}

	protected function dom_id( string $scope, string $id ): string {
		return "{$this->prefix}-$scope-notice-$id";
	}
}
