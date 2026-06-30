<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Integration\Permissions;

use DeepWebSolutions\Framework\Utilities\Permissions\CapabilityRegistrar;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( CapabilityRegistrar::class )]
final class CapabilityRegistrarTest extends TestCase {
	private const ROLE_A = 'dws_test_role_a';
	private const ROLE_B = 'dws_test_role_b';

	protected function setUp(): void {
		parent::setUp();
		\remove_role( self::ROLE_A );
		\remove_role( self::ROLE_B );
		\add_role( self::ROLE_A, 'DWS Test Role A', array() );
		\add_role( self::ROLE_B, 'DWS Test Role B', array() );
	}

	protected function tearDown(): void {
		\remove_role( self::ROLE_A );
		\remove_role( self::ROLE_B );
		parent::tearDown();
	}

	public function test_grant_adds_capabilities_to_a_role(): void {
		( new CapabilityRegistrar() )->grant( array( self::ROLE_A => array( 'dws_edit', 'dws_delete' ) ) );

		$role = \get_role( self::ROLE_A );
		self::assertNotNull( $role );
		self::assertTrue( $role->has_cap( 'dws_edit' ) );
		self::assertTrue( $role->has_cap( 'dws_delete' ) );
	}

	public function test_grant_skips_an_unknown_role_without_error(): void {
		( new CapabilityRegistrar() )->grant(
			array(
				'dws_nonexistent_role' => array( 'dws_edit' ),
				self::ROLE_A           => array( 'dws_edit' ),
			),
		);

		self::assertNull( \get_role( 'dws_nonexistent_role' ) );

		// The unknown role is skipped (continue), not a loop break: a real role listed after it is still granted.
		$role_a = \get_role( self::ROLE_A );
		self::assertNotNull( $role_a );
		self::assertTrue( $role_a->has_cap( 'dws_edit' ) );
	}

	public function test_grant_is_idempotent(): void {
		$registrar = new CapabilityRegistrar();
		$registrar->grant( array( self::ROLE_A => array( 'dws_edit' ) ) );
		$registrar->grant( array( self::ROLE_A => array( 'dws_edit' ) ) );

		$role = \get_role( self::ROLE_A );
		self::assertNotNull( $role );
		self::assertTrue( $role->has_cap( 'dws_edit' ) );
	}

	public function test_reconcile_grants_new_caps_and_revokes_caps_dropped_since_the_prior_version(): void {
		$registrar = new CapabilityRegistrar();
		$registrar->grant( array( self::ROLE_A => array( 'dws_old', 'dws_keep' ) ) );

		$registrar->reconcile(
			array( self::ROLE_A => array( 'dws_keep', 'dws_new' ) ),
			array( self::ROLE_A => array( 'dws_old', 'dws_keep' ) ),
		);

		$role = \get_role( self::ROLE_A );
		self::assertNotNull( $role );
		self::assertTrue( $role->has_cap( 'dws_keep' ) );
		self::assertTrue( $role->has_cap( 'dws_new' ) );
		self::assertFalse( $role->has_cap( 'dws_old' ) );
	}

	public function test_reconcile_moves_a_capability_to_a_new_role(): void {
		$registrar = new CapabilityRegistrar();
		$registrar->grant( array( self::ROLE_A => array( 'dws_move' ) ) );

		$registrar->reconcile(
			array( self::ROLE_B => array( 'dws_move' ) ),
			array( self::ROLE_A => array( 'dws_move' ) ),
		);

		$role_a = \get_role( self::ROLE_A );
		$role_b = \get_role( self::ROLE_B );
		self::assertNotNull( $role_a );
		self::assertNotNull( $role_b );
		self::assertFalse( $role_a->has_cap( 'dws_move' ) );
		self::assertTrue( $role_b->has_cap( 'dws_move' ) );
	}

	public function test_reconcile_revokes_caps_for_a_role_dropped_entirely_from_the_desired_map(): void {
		$registrar = new CapabilityRegistrar();
		$registrar->grant( array( self::ROLE_A => array( 'dws_old' ) ) );

		$registrar->reconcile(
			array( self::ROLE_B => array( 'dws_new' ) ),
			array( self::ROLE_A => array( 'dws_old' ) ),
		);

		$role_a = \get_role( self::ROLE_A );
		$role_b = \get_role( self::ROLE_B );
		self::assertNotNull( $role_a );
		self::assertNotNull( $role_b );
		self::assertFalse( $role_a->has_cap( 'dws_old' ) );
		self::assertTrue( $role_b->has_cap( 'dws_new' ) );
	}

	public function test_revoke_all_removes_the_given_capabilities(): void {
		$registrar = new CapabilityRegistrar();
		$registrar->grant( array( self::ROLE_A => array( 'dws_edit', 'dws_delete' ) ) );

		$registrar->revoke_all( array( self::ROLE_A => array( 'dws_edit', 'dws_delete' ) ) );

		$role = \get_role( self::ROLE_A );
		self::assertNotNull( $role );
		self::assertFalse( $role->has_cap( 'dws_edit' ) );
		self::assertFalse( $role->has_cap( 'dws_delete' ) );
	}

	public function test_grant_persists_a_capability_even_when_a_filter_virtually_grants_it(): void {
		$filter = static fn( mixed $caps ): mixed => \is_array( $caps ) ? $caps + array( 'dws_virtual' => true ) : $caps;
		\add_filter( 'role_has_cap', $filter );

		try {
			( new CapabilityRegistrar() )->grant( array( self::ROLE_A => array( 'dws_virtual' ) ) );
		} finally {
			\remove_filter( 'role_has_cap', $filter );
		}

		// has_cap() saw the filter's virtual grant, but the capability must still be stored on the role.
		$role = \get_role( self::ROLE_A );
		self::assertNotNull( $role );
		self::assertArrayHasKey( 'dws_virtual', $role->capabilities );
	}

	public function test_revoke_all_removes_a_stored_capability_even_when_a_filter_masks_it(): void {
		( new CapabilityRegistrar() )->grant( array( self::ROLE_A => array( 'dws_masked' ) ) );

		$filter = static fn( mixed $caps ): mixed => \is_array( $caps ) ? \array_diff_key( $caps, array( 'dws_masked' => true ) ) : $caps;
		\add_filter( 'role_has_cap', $filter );

		try {
			( new CapabilityRegistrar() )->revoke_all( array( self::ROLE_A => array( 'dws_masked' ) ) );
		} finally {
			\remove_filter( 'role_has_cap', $filter );
		}

		// has_cap() saw the filter mask the cap, but the stored capability must still be removed.
		$role = \get_role( self::ROLE_A );
		self::assertNotNull( $role );
		self::assertArrayNotHasKey( 'dws_masked', $role->capabilities );
	}

	public function test_revoke_all_leaves_capabilities_outside_the_map_untouched(): void {
		$registrar = new CapabilityRegistrar();
		$registrar->grant( array( self::ROLE_A => array( 'dws_edit', 'dws_keep' ) ) );

		$registrar->revoke_all( array( self::ROLE_A => array( 'dws_edit' ) ) );

		$role = \get_role( self::ROLE_A );
		self::assertNotNull( $role );
		self::assertFalse( $role->has_cap( 'dws_edit' ) );
		self::assertTrue( $role->has_cap( 'dws_keep' ) );
	}

	public function test_revoke_all_skips_an_unknown_role_without_error(): void {
		$registrar = new CapabilityRegistrar();
		$registrar->grant( array( self::ROLE_A => array( 'dws_edit' ) ) );

		$registrar->revoke_all(
			array(
				'dws_nonexistent_role' => array( 'dws_edit' ),
				self::ROLE_A           => array( 'dws_edit' ),
			),
		);

		self::assertNull( \get_role( 'dws_nonexistent_role' ) );

		// The unknown role is skipped (continue), not a loop break: a real role listed after it is still revoked.
		$role_a = \get_role( self::ROLE_A );
		self::assertNotNull( $role_a );
		self::assertFalse( $role_a->has_cap( 'dws_edit' ) );
	}
}
