<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Integration\Hooks;

use DeepWebSolutions\Framework\Utilities\Hooks\DeprecatedHooksDispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( DeprecatedHooksDispatcher::class )]
final class DeprecatedHooksDispatcherTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		// Keep the deprecated-hook pathway from emitting E_USER_DEPRECATED under WP_DEBUG;
		// the hook itself still fires, which is what these tests assert.
		\add_filter( 'deprecated_hook_trigger_error', '__return_false' );
	}

	protected function tearDown(): void {
		\remove_filter( 'deprecated_hook_trigger_error', '__return_false' );
		foreach (
			array(
				'dws_current_action',
				'dws_legacy_action',
				'dws_current_args',
				'dws_legacy_args',
				'dws_current_filter',
				'dws_legacy_filter',
			) as $hook
		) {
			\remove_all_actions( $hook );
			\remove_all_filters( $hook );
		}
		parent::tearDown();
	}

	public function test_do_action_pair_fires_current_then_deprecated(): void {
		$fired = array();
		\add_action(
			'dws_current_action',
			static function () use ( &$fired ): void {
				$fired[] = 'current';
			},
		);
		\add_action(
			'dws_legacy_action',
			static function () use ( &$fired ): void {
				$fired[] = 'legacy';
			},
		);

		( new DeprecatedHooksDispatcher() )->do_action_pair( 'dws_current_action', 'dws_legacy_action', '2.0.0' );

		self::assertSame( array( 'current', 'legacy' ), $fired );
	}

	public function test_do_action_pair_passes_args_to_both_hooks(): void {
		$current = null;
		$legacy  = null;
		\add_action(
			'dws_current_args',
			static function ( $a, $b ) use ( &$current ): void {
				$current = array( $a, $b );
			},
			10,
			2,
		);
		\add_action(
			'dws_legacy_args',
			static function ( $a, $b ) use ( &$legacy ): void {
				$legacy = array( $a, $b );
			},
			10,
			2,
		);

		( new DeprecatedHooksDispatcher() )->do_action_pair( 'dws_current_args', 'dws_legacy_args', '2.0.0', 'x', 'y' );

		self::assertSame( array( 'x', 'y' ), $current );
		self::assertSame( array( 'x', 'y' ), $legacy );
	}

	public function test_apply_filters_pair_chains_current_then_deprecated(): void {
		\add_filter( 'dws_current_filter', static fn ( $v ) => $v . '-current' );
		\add_filter( 'dws_legacy_filter', static fn ( $v ) => $v . '-legacy' );

		$result = ( new DeprecatedHooksDispatcher() )->apply_filters_pair( 'dws_current_filter', 'dws_legacy_filter', '2.0.0', 'base' );

		self::assertSame( 'base-current-legacy', $result );
	}

	public function test_apply_filters_pair_forwards_extra_args_to_both_filters(): void {
		$current_received = null;
		$legacy_received  = null;
		\add_filter(
			'dws_current_filter',
			static function ( $value, $extra ) use ( &$current_received ) {
				$current_received = $extra;
				return $value . '-current';
			},
			10,
			2,
		);
		\add_filter(
			'dws_legacy_filter',
			static function ( $value, $extra ) use ( &$legacy_received ) {
				$legacy_received = array( $value, $extra );
				return $value . '-legacy';
			},
			10,
			2,
		);

		$result = ( new DeprecatedHooksDispatcher() )->apply_filters_pair( 'dws_current_filter', 'dws_legacy_filter', '2.0.0', 'base', 'extra' );

		// The legacy filter receives the current-filtered value plus the same trailing extra arg, in order.
		self::assertSame( 'extra', $current_received );
		self::assertSame( array( 'base-current', 'extra' ), $legacy_received );
		self::assertSame( 'base-current-legacy', $result );
	}
}
