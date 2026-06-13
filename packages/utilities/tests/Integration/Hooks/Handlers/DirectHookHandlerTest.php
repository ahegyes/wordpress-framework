<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Integration\Hooks\Handlers;

use DeepWebSolutions\Framework\Utilities\Hooks\Handlers\DirectHookHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( DirectHookHandler::class )]
final class DirectHookHandlerTest extends TestCase {
	public function test_add_action_registers_with_wordpress_and_remove_all_reverts(): void {
		$handler = new DirectHookHandler( 'direct-test' );
		$cb      = static function (): void {};

		$handler->add_action( 'dws_direct_action', $cb, 10, 1 );
		self::assertNotFalse( \has_action( 'dws_direct_action', $cb ) );

		$handler->remove_all_actions();
		self::assertFalse( \has_action( 'dws_direct_action', $cb ) );
	}

	public function test_add_filter_registers_with_wordpress_and_remove_all_reverts(): void {
		$handler = new DirectHookHandler( 'direct-test' );
		$cb      = static fn ( $v ) => $v;

		$handler->add_filter( 'dws_direct_filter', $cb, 10, 1 );
		self::assertNotFalse( \has_filter( 'dws_direct_filter', $cb ) );

		$handler->remove_all_filters();
		self::assertFalse( \has_filter( 'dws_direct_filter', $cb ) );
	}

	public function test_remove_action_unregisters_single_callback(): void {
		$handler = new DirectHookHandler( 'direct-test' );
		$cb      = static function (): void {};

		$handler->add_action( 'dws_direct_single', $cb, 10, 1 );

		self::assertTrue( $handler->remove_action( 'dws_direct_single', $cb, 10 ) );
		self::assertFalse( \has_action( 'dws_direct_single', $cb ) );
	}

	public function test_remove_action_returns_false_when_not_recorded(): void {
		$handler = new DirectHookHandler( 'direct-test' );
		$cb      = static function (): void {};

		self::assertFalse( $handler->remove_action( 'dws_direct_unknown', $cb, 10 ) );
	}
}
