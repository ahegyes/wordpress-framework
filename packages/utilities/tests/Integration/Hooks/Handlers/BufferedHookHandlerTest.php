<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Integration\Hooks\Handlers;

use DeepWebSolutions\Framework\Utilities\Hooks\Handlers\BufferedHookHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( BufferedHookHandler::class )]
final class BufferedHookHandlerTest extends TestCase {
	public function test_flush_registers_queued_actions_then_reset_removes_them(): void {
		$handler = new BufferedHookHandler( 'buffered-test' );
		$cb      = static function (): void {};

		$handler->add_action( 'dws_buffered_action', $cb, 10, 1 );
		// Queued only — not registered with WordPress until flush().
		self::assertFalse( \has_action( 'dws_buffered_action', $cb ) );

		$handler->flush();
		self::assertNotFalse( \has_action( 'dws_buffered_action', $cb ) );

		$handler->reset();
		self::assertFalse( \has_action( 'dws_buffered_action', $cb ) );
	}

	public function test_flush_registers_queued_filters(): void {
		$handler = new BufferedHookHandler( 'buffered-test' );
		$cb      = static fn ( $v ) => $v;

		$handler->add_filter( 'dws_buffered_filter', $cb, 10, 1 );
		$handler->flush();
		self::assertNotFalse( \has_filter( 'dws_buffered_filter', $cb ) );

		$handler->reset();
		self::assertFalse( \has_filter( 'dws_buffered_filter', $cb ) );
	}

	public function test_remove_action_drops_record_and_unregisters_flushed_hook(): void {
		$handler = new BufferedHookHandler( 'buffered-rm' );
		$cb      = static function (): void {};
		$handler->add_action( 'dws_buffered_rm', $cb, 10, 1 );
		$handler->flush();
		self::assertNotFalse( \has_action( 'dws_buffered_rm', $cb ) );

		self::assertTrue( $handler->remove_action( 'dws_buffered_rm', $cb, 10 ) );
		// The live registration is gone, not just the queued record.
		self::assertFalse( \has_action( 'dws_buffered_rm', $cb ) );
		self::assertSame( array(), $handler->get_registry()->get_actions() );
	}

	public function test_remove_action_returns_false_when_not_queued(): void {
		$handler = new BufferedHookHandler( 'buffered-rm' );
		$cb      = static function (): void {};

		self::assertFalse( $handler->remove_action( 'dws_buffered_absent', $cb, 10 ) );
	}

	public function test_remove_filter_drops_record_and_unregisters_flushed_hook(): void {
		$handler = new BufferedHookHandler( 'buffered-rm' );
		$cb      = static fn ( $v ) => $v;
		$handler->add_filter( 'dws_buffered_rm_filter', $cb, 10, 1 );
		$handler->flush();
		self::assertNotFalse( \has_filter( 'dws_buffered_rm_filter', $cb ) );

		self::assertTrue( $handler->remove_filter( 'dws_buffered_rm_filter', $cb, 10 ) );
		// The live registration is gone, not just the queued record.
		self::assertFalse( \has_filter( 'dws_buffered_rm_filter', $cb ) );
		self::assertSame( array(), $handler->get_registry()->get_filters() );
	}

	public function test_remove_filter_returns_false_when_not_queued(): void {
		$handler = new BufferedHookHandler( 'buffered-rm' );
		$cb      = static fn ( $v ) => $v;

		self::assertFalse( $handler->remove_filter( 'dws_buffered_absent_filter', $cb, 10 ) );
	}

	public function test_remove_all_actions_unregisters_flushed_hooks(): void {
		$handler = new BufferedHookHandler( 'buffered-rm' );
		$cb      = static function (): void {};
		$handler->add_action( 'dws_buffered_all_a', $cb, 10, 1 );
		$handler->add_action( 'dws_buffered_all_b', $cb, 20, 1 );
		$handler->flush();

		$handler->remove_all_actions();

		self::assertFalse( \has_action( 'dws_buffered_all_a', $cb ) );
		self::assertFalse( \has_action( 'dws_buffered_all_b', $cb ) );
		self::assertSame( array(), $handler->get_registry()->get_actions() );
	}

	public function test_remove_all_filters_unregisters_flushed_hooks(): void {
		$handler = new BufferedHookHandler( 'buffered-rm' );
		$cb      = static fn ( $v ) => $v;
		$handler->add_filter( 'dws_buffered_filter_all', $cb, 10, 1 );
		$handler->flush();

		$handler->remove_all_filters();

		self::assertFalse( \has_filter( 'dws_buffered_filter_all', $cb ) );
		self::assertSame( array(), $handler->get_registry()->get_filters() );
	}
}
