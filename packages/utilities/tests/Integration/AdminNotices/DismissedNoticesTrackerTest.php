<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Integration\AdminNotices;

use DeepWebSolutions\Framework\Utilities\AdminNotices\DismissedNoticesTracker;
use DeepWebSolutions\Framework\Utilities\Storage\UserMetaStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( DismissedNoticesTracker::class )]
#[UsesClass( UserMetaStore::class )]
final class DismissedNoticesTrackerTest extends TestCase {
	private const META_KEY = 'dws_test_dismissed_notices';

	private int $user_a;
	private int $user_b;
	private int $original_user;

	protected function setUp(): void {
		parent::setUp();
		$this->original_user = \get_current_user_id();
		$this->user_a        = $this->make_user( 'dws_dismiss_a' );
		$this->user_b        = $this->make_user( 'dws_dismiss_b' );
		\wp_set_current_user( $this->user_a );
	}

	protected function tearDown(): void {
		\wp_set_current_user( $this->original_user );
		$this->delete_user( $this->user_a );
		$this->delete_user( $this->user_b );
		parent::tearDown();
	}

	public function test_dismiss_then_is_dismissed_is_true(): void {
		$tracker = new DismissedNoticesTracker( new UserMetaStore( self::META_KEY ) );

		$tracker->dismiss( 'dep_woocommerce' );

		self::assertTrue( $tracker->is_dismissed( 'dep_woocommerce' ) );
	}

	public function test_is_dismissed_is_false_for_an_undismissed_notice(): void {
		$tracker = new DismissedNoticesTracker( new UserMetaStore( self::META_KEY ) );

		self::assertFalse( $tracker->is_dismissed( 'never_dismissed' ) );
	}

	public function test_dismissals_persist_across_instances(): void {
		( new DismissedNoticesTracker( new UserMetaStore( self::META_KEY ) ) )->dismiss( 'dep_woocommerce' );

		$fresh = new DismissedNoticesTracker( new UserMetaStore( self::META_KEY ) );

		self::assertTrue( $fresh->is_dismissed( 'dep_woocommerce' ) );
	}

	public function test_dismissals_are_isolated_per_user(): void {
		$tracker = new DismissedNoticesTracker( new UserMetaStore( self::META_KEY ) );
		$tracker->dismiss( 'dep_woocommerce' );

		\wp_set_current_user( $this->user_b );
		$for_b = new DismissedNoticesTracker( new UserMetaStore( self::META_KEY ) );

		self::assertFalse( $for_b->is_dismissed( 'dep_woocommerce' ) );
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
