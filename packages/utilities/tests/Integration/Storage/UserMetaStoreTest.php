<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Integration\Storage;

use DeepWebSolutions\Framework\Utilities\Storage\UserMetaStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( UserMetaStore::class )]
final class UserMetaStoreTest extends TestCase {
	private const META_KEY = 'dws_test_user_meta_store';

	private int $user_a;
	private int $user_b;
	private int $original_user;

	protected function setUp(): void {
		parent::setUp();
		$this->original_user = \get_current_user_id();
		$this->user_a        = $this->make_user( 'dws_user_a' );
		$this->user_b        = $this->make_user( 'dws_user_b' );
		\wp_set_current_user( $this->user_a );
	}

	protected function tearDown(): void {
		\wp_set_current_user( $this->original_user );
		$this->delete_user( $this->user_a );
		$this->delete_user( $this->user_b );
		parent::tearDown();
	}

	public function test_set_then_get_round_trips_for_current_user(): void {
		$store = new UserMetaStore( self::META_KEY );
		$store->set( 'k', 'v' );

		self::assertSame( 'v', $store->get( 'k' ) );
	}

	public function test_get_returns_stored_null_over_non_null_default(): void {
		$store = new UserMetaStore( self::META_KEY );
		$store->set( 'k', null );

		self::assertTrue( $store->has( 'k' ) );
		self::assertNull( $store->get( 'k', 'sentinel' ) );
	}

	public function test_explicit_user_id_targets_that_user(): void {
		$store = new UserMetaStore( self::META_KEY );
		$store->set( 'k', 'for-b', $this->user_b );

		self::assertSame( 'for-b', $store->get( 'k', null, $this->user_b ) );
		// The current user (A) has nothing stored under the key.
		self::assertNull( $store->get( 'k' ) );
	}

	public function test_users_are_isolated(): void {
		$store = new UserMetaStore( self::META_KEY );
		$store->set( 'k', 'a-value' );
		$store->set( 'k', 'b-value', $this->user_b );

		self::assertSame( 'a-value', $store->get( 'k' ) );
		self::assertSame( 'b-value', $store->get( 'k', null, $this->user_b ) );
	}

	public function test_delete_returns_true_when_value_existed_and_false_when_absent(): void {
		$store = new UserMetaStore( self::META_KEY );
		$store->set( 'k', 'v' );

		self::assertTrue( $store->delete( 'k' ) );
		self::assertFalse( $store->has( 'k' ) );
		self::assertFalse( $store->delete( 'k' ) );
	}

	public function test_delete_persists_across_instances(): void {
		( new UserMetaStore( self::META_KEY ) )->set( 'k', 'v' );
		( new UserMetaStore( self::META_KEY ) )->delete( 'k' );

		self::assertFalse( ( new UserMetaStore( self::META_KEY ) )->has( 'k' ) );
	}

	public function test_delete_targets_explicit_user(): void {
		$store = new UserMetaStore( self::META_KEY );
		$store->set( 'k', 'v', $this->user_b );

		self::assertTrue( $store->delete( 'k', $this->user_b ) );
		self::assertFalse( $store->has( 'k', $this->user_b ) );
	}

	public function test_clear_removes_all_entries_for_user(): void {
		$store = new UserMetaStore( self::META_KEY );
		$store->set( 'a', 1 );
		$store->set( 'b', 2 );

		$store->clear();

		self::assertSame( array(), $store->get_all() );
	}

	public function test_anonymous_user_writes_are_noops(): void {
		\wp_set_current_user( 0 );
		$store = new UserMetaStore( self::META_KEY );

		$store->set( 'k', 'v' );

		self::assertFalse( $store->has( 'k' ) );
		self::assertSame( 'default', $store->get( 'k', 'default' ) );
		self::assertSame( array(), $store->get_all() );
	}

	public function test_anonymous_user_delete_and_clear_are_noops(): void {
		\wp_set_current_user( 0 );
		$store = new UserMetaStore( self::META_KEY );

		self::assertFalse( $store->delete( 'k' ) );
		$store->clear();
		self::assertSame( array(), $store->get_all() );
	}

	public function test_corrupted_meta_value_falls_back_to_empty(): void {
		\update_user_meta( $this->user_a, self::META_KEY, 'not-an-array' );
		$store = new UserMetaStore( self::META_KEY );

		self::assertSame( array(), $store->get_all() );
		self::assertSame( 'default', $store->get( 'k', 'default' ) );
	}

	public function test_preserves_backslashes_in_stored_string_values(): void {
		$store = new UserMetaStore( self::META_KEY );
		$store->set( 'path', 'C:\\Users\\dev\\file.txt' );

		// update_user_meta() runs the value through wp_unslash(); without a compensating wp_slash()
		// the backslashes would be stripped to 'C:Usersdevfile.txt'.
		self::assertSame( 'C:\\Users\\dev\\file.txt', $store->get( 'path' ) );
	}

	private function make_user( string $login ): int {
		$existing = \get_user_by( 'login', $login );
		if ( $existing instanceof \WP_User ) {
			return $existing->ID;
		}

		$id = \wp_insert_user(
			array(
				'user_login' => $login,
				'user_pass'  => 'password',
				'role'       => 'subscriber',
			),
		);
		self::assertIsInt( $id );
		return $id;
	}

	private function delete_user( int $id ): void {
		if ( ! \function_exists( 'wp_delete_user' ) ) {
			require_once \ABSPATH . 'wp-admin/includes/user.php';
		}
		\wp_delete_user( $id );
	}
}
