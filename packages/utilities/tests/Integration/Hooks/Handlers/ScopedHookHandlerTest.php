<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Integration\Hooks\Handlers;

use DeepWebSolutions\Framework\Utilities\Hooks\Handlers\ScopedHookHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( ScopedHookHandler::class )]
final class ScopedHookHandlerTest extends TestCase {
	protected function tearDown(): void {
		foreach (
			array(
				'dws_scope_start',
				'dws_scope_end',
				'dws_scoped_target',
				'dws_scope_start_2',
				'dws_scoped_target_2',
				'dws_scope_idem_start',
				'dws_scope_rm_target',
			) as $hook
		) {
			\remove_all_actions( $hook );
		}
		parent::tearDown();
	}

	public function test_lifecycle_flushes_on_start_and_resets_on_end(): void {
		$handler = new ScopedHookHandler( 'scoped-test', 'dws_scope_start', 'dws_scope_end' );
		$cb      = static function (): void {};
		$handler->add_action( 'dws_scoped_target', $cb, 10, 1 );
		$handler->register_lifecycle();

		// Nothing registered until the start hook fires.
		self::assertFalse( \has_action( 'dws_scoped_target', $cb ) );

		\do_action( 'dws_scope_start' );
		self::assertNotFalse( \has_action( 'dws_scoped_target', $cb ) );

		\do_action( 'dws_scope_end' );
		self::assertFalse( \has_action( 'dws_scoped_target', $cb ) );
	}

	public function test_without_end_hook_registrations_persist_after_start(): void {
		$handler = new ScopedHookHandler( 'scoped-persist', 'dws_scope_start_2' );
		$cb      = static function (): void {};
		$handler->add_action( 'dws_scoped_target_2', $cb, 10, 1 );
		$handler->register_lifecycle();

		\do_action( 'dws_scope_start_2' );
		self::assertNotFalse( \has_action( 'dws_scoped_target_2', $cb ) );
	}

	public function test_register_lifecycle_is_idempotent(): void {
		$handler = new ScopedHookHandler( 'scoped-idem', 'dws_scope_idem_start' );
		$handler->register_lifecycle();
		$handler->register_lifecycle();

		// Stable [object, method] callbacks let WordPress de-duplicate the repeat registration.
		$registered = $GLOBALS['wp_filter']['dws_scope_idem_start'] ?? null;
		self::assertInstanceOf( \WP_Hook::class, $registered );
		self::assertCount( 1, $registered->callbacks[10] );
	}

	public function test_remove_all_actions_delegates_to_buffer(): void {
		$handler = new ScopedHookHandler( 'scoped-rm', 'dws_scope_rm_start' );
		$cb      = static function (): void {};
		$handler->add_action( 'dws_scope_rm_target', $cb, 10, 1 );

		$handler->remove_all_actions();

		self::assertSame( array(), $handler->get_buffer()->get_registry()->get_actions() );
	}
}
