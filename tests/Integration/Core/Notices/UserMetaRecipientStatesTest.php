<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Core\Notices;

use DeepWebSolutions\Framework\Core\AdminNotice\NoticeContext;
use DeepWebSolutions\Framework\Core\AdminNotice\NoticeIdentity;
use DeepWebSolutions\Framework\Core\AdminNotice\NoticeScope;
use DeepWebSolutions\Framework\Core\AdminNotice\RecipientState;
use DeepWebSolutions\Framework\Core\AdminNotice\Stores\UserMetaRecipientStates;
use DeepWebSolutions\Framework\Shared\Exception\InvalidArgumentException;
use DeepWebSolutions\Framework\Shared\Exception\LogicException;
use DeepWebSolutions\Framework\Shared\Exception\RuntimeException;
use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesSitesTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesStorageTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass( UserMetaRecipientStates::class )]
final class UserMetaRecipientStatesTest extends TestCase {
	use CreatesSitesTrait;
	use CreatesUsersTrait;
	use IsolatesHooksTrait;
	use IsolatesStorageTrait;

	protected int $user_id;

	#[\Override]
	protected function setUp(): void {
		$this->user_id = $this->make_user( 'acme_notices_admin', 'administrator' );
	}

	#[\Override]
	protected function tearDown(): void {
		while ( \is_multisite() && \ms_is_switched() ) {
			\restore_current_blog();
		}
	}

	public function test_a_state_reads_back_once_recorded_and_recording_it_again_changes_nothing(): void {
		$states   = new UserMetaRecipientStates( $this->prefix );
		$identity = $this->identity( NoticeScope::Site, 'one' );
		self::assertNull( $states->get( $identity, $this->user_id ) );

		self::assertTrue( $states->record( $identity, $this->user_id, RecipientState::Delivered ) );
		$rows = $this->rows( $identity );
		self::assertFalse( $states->record( $identity, $this->user_id, RecipientState::Delivered ) );

		self::assertSame( RecipientState::Delivered, ( new UserMetaRecipientStates( $this->prefix ) )->get( $identity, $this->user_id ) );
		self::assertSame( $rows, $this->rows( $identity ) );
		self::assertSame( array( 'v', 'notice', 'state', 'at' ), \array_keys( $rows[0] ) );
		self::assertSame( array( 1, 'site:1:one', 'delivered' ), array( $rows[0]['v'], $rows[0]['notice'], $rows[0]['state'] ) );
	}

	public function test_another_state_replaces_the_recorded_one(): void {
		$states   = new UserMetaRecipientStates( $this->prefix );
		$identity = $this->identity( NoticeScope::Site, 'one' );
		$states->record( $identity, $this->user_id, RecipientState::Delivered );

		self::assertTrue( $states->record( $identity, $this->user_id, RecipientState::Dismissed ) );

		self::assertSame( RecipientState::Dismissed, $states->get( $identity, $this->user_id ) );
		self::assertCount( 1, $this->rows( $identity ) );
	}

	public function test_identities_of_other_scopes_ids_and_users_keep_their_own_state(): void {
		$states    = new UserMetaRecipientStates( $this->prefix );
		$dismissed = $this->identity( NoticeScope::Request, 'one' );
		$states->record( $dismissed, $this->user_id, RecipientState::Dismissed );
		$states->record( $this->identity( NoticeScope::Request, 'two' ), $this->user_id, RecipientState::Delivered );

		self::assertNull( $states->get( $this->identity( NoticeScope::Site, 'one' ), $this->user_id ) );
		self::assertNull( $states->get( $this->identity( NoticeScope::Network, 'one' ), $this->user_id ) );
		self::assertNull( $states->get( $dismissed, $this->make_user( 'acme_notices_editor', 'editor' ) ) );
		self::assertSame( RecipientState::Dismissed, $states->get( $dismissed, $this->user_id ) );
	}

	public function test_a_record_racing_another_for_the_same_notice_never_deletes_its_row(): void {
		$states   = new UserMetaRecipientStates( $this->prefix );
		$identity = $this->identity( NoticeScope::Site, 'one' );
		$racing   = false;
		\add_action(
			'added_user_meta',
			function () use ( $identity, &$racing ): void {
				if ( ! $racing ) {
					$racing = true;
					( new UserMetaRecipientStates( $this->prefix ) )->record( $identity, $this->user_id, RecipientState::Dismissed );
				}
			}
		);

		$states->record( $identity, $this->user_id, RecipientState::Delivered );

		self::assertSame( RecipientState::Dismissed, ( new UserMetaRecipientStates( $this->prefix ) )->get( $identity, $this->user_id ) );
	}

	public function test_a_record_racing_another_keeps_its_row_when_it_equals_a_row_the_other_deletes(): void {
		$identity = $this->identity( NoticeScope::Site, 'one' );
		$key      = $GLOBALS['wpdb']->get_blog_prefix() . "{$this->prefix}_notice_states";
		\add_user_meta(
			$this->user_id,
			$key,
			array(
				'v'      => 1,
				'notice' => $identity->to_key(),
				'state'  => 'dismissed',
				'at'     => \time(),
			)
		);
		$racing = false;
		\add_action(
			'added_user_meta',
			function () use ( $identity, &$racing ): void {
				if ( ! $racing ) {
					$racing = true;
					( new UserMetaRecipientStates( $this->prefix ) )->record( $identity, $this->user_id, RecipientState::Dismissed );
				}
			}
		);

		( new UserMetaRecipientStates( $this->prefix ) )->record( $identity, $this->user_id, RecipientState::Delivered );

		self::assertSame( RecipientState::Dismissed, ( new UserMetaRecipientStates( $this->prefix ) )->get( $identity, $this->user_id ) );
	}

	public function test_of_two_rows_for_one_identity_the_later_is_read(): void {
		$identity = $this->identity( NoticeScope::Site, 'one' );
		foreach ( array( 'dismissed', 'delivered' ) as $state ) {
			\add_user_meta(
				$this->user_id,
				$GLOBALS['wpdb']->get_blog_prefix() . "{$this->prefix}_notice_states",
				array(
					'v'      => 1,
					'notice' => $identity->to_key(),
					'state'  => $state,
					'at'     => 1,
				)
			);
		}

		self::assertSame( RecipientState::Delivered, ( new UserMetaRecipientStates( $this->prefix ) )->get( $identity, $this->user_id ) );
	}

	public function test_a_failed_insert_throws_and_keeps_the_previous_state(): void {
		$states   = new UserMetaRecipientStates( $this->prefix );
		$identity = $this->identity( NoticeScope::Site, 'one' );
		$states->record( $identity, $this->user_id, RecipientState::Delivered );
		\add_filter( 'add_user_metadata', '__return_false' );

		try {
			$states->record( $identity, $this->user_id, RecipientState::Dismissed );
			self::fail( 'The failed insert did not throw.' );
		} catch ( RuntimeException ) {
			self::assertSame( RecipientState::Delivered, $states->get( $identity, $this->user_id ) );
		}
	}

	public function test_rows_that_are_not_valid_states_read_as_no_state_and_are_kept(): void {
		$states   = new UserMetaRecipientStates( $this->prefix );
		$identity = $this->identity( NoticeScope::Site, 'one' );
		$key      = $GLOBALS['wpdb']->get_blog_prefix() . "{$this->prefix}_notice_states";
		$valid    = array(
			'v'      => 1,
			'notice' => 'site:1:one',
			'state'  => 'dismissed',
			'at'     => 1,
		);
		$rows     = array( 'garbage', array( 'v' => 2 ) + $valid, \array_diff_key( $valid, array( 'state' => true ) ), array( 'state' => 'snoozed' ) + $valid, array( 'at' => '1' ) + $valid );
		foreach ( $rows as $row ) {
			\add_user_meta( $this->user_id, $key, $row );
		}

		self::assertNull( $states->get( $identity, $this->user_id ) );
		$states->record( $identity, $this->user_id, RecipientState::Delivered );
		self::assertSame( array( 'garbage', array( 'v' => 2 ) + $valid, \array_diff_key( $valid, array( 'state' => true ) ), array( 'at' => '1' ) + $valid ), \array_slice( \get_user_meta( $this->user_id, $key, false ), 0, 4 ) );
	}

	public static function invalid_users(): array {
		return array(
			'get-zero'        => array( static fn ( UserMetaRecipientStates $states, NoticeIdentity $identity ): mixed => $states->get( $identity, 0 ) ),
			'record-negative' => array( static fn ( UserMetaRecipientStates $states, NoticeIdentity $identity ): mixed => $states->record( $identity, -1, RecipientState::Dismissed ) ),
		);
	}

	#[DataProvider( 'invalid_users' )]
	public function test_a_user_id_below_1_is_rejected( \Closure $call ): void {
		$this->expectException( InvalidArgumentException::class );

		$call( new UserMetaRecipientStates( $this->prefix ), $this->identity( NoticeScope::Site, 'one' ) );
	}

	public static function keys(): array {
		return array(
			'request' => array( NoticeScope::Request, true ),
			'user'    => array( NoticeScope::User, true ),
			'site'    => array( NoticeScope::Site, true ),
			'network' => array( NoticeScope::Network, false ),
		);
	}

	#[DataProvider( 'keys' )]
	public function test_network_states_live_under_an_unprefixed_network_key_and_the_others_under_the_sites_key( NoticeScope $scope, bool $site_prefixed ): void {
		global $wpdb;

		( new UserMetaRecipientStates( $this->prefix ) )->record( $this->identity( $scope, 'one' ), $this->user_id, RecipientState::Dismissed );

		$key = $site_prefixed ? $wpdb->get_blog_prefix() . "{$this->prefix}_notice_states" : "{$this->prefix}_network_notice_states";
		self::assertCount( 1, \get_user_meta( $this->user_id, $key, false ) );
	}

	public function test_without_a_database_connection_the_store_cannot_be_built(): void {
		$wpdb            = $GLOBALS['wpdb'];
		$GLOBALS['wpdb'] = null;

		try {
			$this->expectException( LogicException::class );
			new UserMetaRecipientStates( $this->prefix );
		} finally {
			$GLOBALS['wpdb'] = $wpdb;
		}
	}

	#[Group( 'multisite' )]
	public function test_a_network_dismissal_on_one_site_holds_on_every_site_while_site_states_stay_per_site(): void {
		global $wpdb;
		$site_id = $this->make_site( 'acme-notices' );
		\switch_to_blog( $site_id );
		$network = $this->identity( NoticeScope::Network, 'one' );
		$site    = $this->identity( NoticeScope::Site, 'one' );
		$request = $this->identity( NoticeScope::Request, 'one' );
		$user    = $this->identity( NoticeScope::User, 'one' );
		foreach ( array( $network, $site, $request, $user ) as $identity ) {
			( new UserMetaRecipientStates( $this->prefix ) )->record( $identity, $this->user_id, RecipientState::Dismissed );
		}
		\restore_current_blog();

		$states = new UserMetaRecipientStates( $this->prefix );
		self::assertSame( RecipientState::Dismissed, $states->get( $network, $this->user_id ) );
		self::assertSame( RecipientState::Dismissed, $states->get( $site, $this->user_id ) );
		self::assertSame( RecipientState::Dismissed, $states->get( $user, $this->user_id ) );
		self::assertNull( $states->get( $request, $this->user_id ) );
		self::assertCount( 3, \get_user_meta( $this->user_id, $wpdb->get_blog_prefix( $site_id ) . "{$this->prefix}_notice_states", false ) );
		self::assertSame( array(), \get_user_meta( $this->user_id, $wpdb->get_blog_prefix() . "{$this->prefix}_notice_states", false ) );
	}

	protected function identity( NoticeScope $scope, string $id ): NoticeIdentity {
		return new NoticeIdentity( NoticeContext::current( $scope, NoticeScope::User === $scope ? $this->user_id : 0 ), $id );
	}

	protected function rows( NoticeIdentity $identity ): array {
		global $wpdb;

		$key = NoticeScope::Network === $identity->context->scope ? "{$this->prefix}_network_notice_states" : $wpdb->get_blog_prefix() . "{$this->prefix}_notice_states";

		return \get_user_meta( $this->user_id, $key, false );
	}
}
