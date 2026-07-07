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
				'dws_scope_idem_target',
				'dws_scope_rm_start',
				'dws_scope_rm_target',
				'dws_scope_filter_start',
				'dws_scope_filter_end',
				'dws_scoped_filter_target',
				'dws_scope_filter_rm_start',
				'dws_scoped_filter_rm_target',
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
		$handler->register_hooks();

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
		$handler->register_hooks();

		\do_action( 'dws_scope_start_2' );
		self::assertNotFalse( \has_action( 'dws_scoped_target_2', $cb ) );
	}

	public function test_register_hooks_is_idempotent(): void {
		$handler = new ScopedHookHandler( 'scoped-idem', 'dws_scope_idem_start' );
		$count   = 0;
		$cb      = static function () use ( &$count ): void {
			++$count;
		};
		$handler->add_action( 'dws_scope_idem_target', $cb, 10, 1 );
		$handler->register_hooks();
		$handler->register_hooks();

		// Two register_hooks calls wire a single flush (stable callbacks de-dup), so one
		// start fire registers the queued callback exactly once — it runs once when fired.
		\do_action( 'dws_scope_idem_start' );
		\do_action( 'dws_scope_idem_target' );

		self::assertSame( 1, $count );
	}

	public function test_lifecycle_flushes_and_resets_scoped_filters(): void {
		$handler = new ScopedHookHandler( 'scoped-filter', 'dws_scope_filter_start', 'dws_scope_filter_end' );
		$cb      = static fn ( $v ) => $v;
		$handler->add_filter( 'dws_scoped_filter_target', $cb, 10, 1 );
		$handler->register_hooks();

		// Nothing registered until the start hook fires.
		self::assertFalse( \has_filter( 'dws_scoped_filter_target', $cb ) );

		\do_action( 'dws_scope_filter_start' );
		self::assertNotFalse( \has_filter( 'dws_scoped_filter_target', $cb ) );

		\do_action( 'dws_scope_filter_end' );
		self::assertFalse( \has_filter( 'dws_scoped_filter_target', $cb ) );
	}

	public function test_remove_all_actions_clears_scoped_action_queue(): void {
		$handler = new ScopedHookHandler( 'scoped-rm', 'dws_scope_rm_start' );
		$cb      = static function (): void {};
		$handler->add_action( 'dws_scope_rm_target', $cb, 10, 1 );

		$handler->remove_all_actions();
		$handler->register_hooks();
		\do_action( 'dws_scope_rm_start' );

		// The queue was emptied before flush, so the start hook registers nothing.
		self::assertFalse( \has_action( 'dws_scope_rm_target', $cb ) );
	}

	public function test_remove_all_filters_clears_scoped_filter_queue(): void {
		$handler = new ScopedHookHandler( 'scoped-filter-rm', 'dws_scope_filter_rm_start' );
		$cb      = static fn ( $v ) => $v;
		$handler->add_filter( 'dws_scoped_filter_rm_target', $cb, 10, 1 );

		$handler->remove_all_filters();
		$handler->register_hooks();
		\do_action( 'dws_scope_filter_rm_start' );

		// The queue was emptied before flush, so the start hook registers nothing.
		self::assertFalse( \has_filter( 'dws_scoped_filter_rm_target', $cb ) );
	}
}
