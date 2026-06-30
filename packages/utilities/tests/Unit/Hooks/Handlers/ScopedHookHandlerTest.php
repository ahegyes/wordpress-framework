<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Unit\Hooks\Handlers;

use DeepWebSolutions\Framework\Utilities\Hooks\Handlers\BufferedHookHandler;
use DeepWebSolutions\Framework\Utilities\Hooks\Handlers\ScopedHookHandler;
use DeepWebSolutions\Framework\Utilities\Hooks\HookRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( ScopedHookHandler::class )]
#[UsesClass( BufferedHookHandler::class )]
#[UsesClass( HookRegistry::class )]
final class ScopedHookHandlerTest extends TestCase {
	public function test_id_and_scope_hooks_are_returned(): void {
		$handler = new ScopedHookHandler( 'admin', 'admin_init', 'admin_footer' );

		self::assertSame( 'admin', $handler->get_id() );
		self::assertSame( 'admin_init', $handler->get_start_hook() );
		self::assertSame( 'admin_footer', $handler->get_end_hook() );
	}

	public function test_end_hook_defaults_to_empty(): void {
		$handler = new ScopedHookHandler( 'persistent', 'init' );

		self::assertSame( '', $handler->get_end_hook() );
	}

	public function test_add_action_delegates_to_buffer(): void {
		$buffer  = new BufferedHookHandler( 'inner', new HookRegistry() );
		$handler = new ScopedHookHandler( 'admin', 'admin_init', '', $buffer );
		$cb      = static function (): void {};

		$handler->add_action( 'init', $cb, 7, 3 );

		$delegated = $buffer->get_registry()->get_actions();
		self::assertCount( 1, $delegated );
		self::assertSame( 'init', $delegated[0]['hook'] );
		self::assertSame( 7, $delegated[0]['priority'] );
		self::assertSame( 3, $delegated[0]['accepted_args'] );
	}

	public function test_add_filter_delegates_to_buffer(): void {
		$buffer  = new BufferedHookHandler( 'inner', new HookRegistry() );
		$handler = new ScopedHookHandler( 'admin', 'admin_init', '', $buffer );
		$cb      = static fn ( $v ) => $v;

		$handler->add_filter( 'the_content', $cb, 5, 2 );

		$delegated = $buffer->get_registry()->get_filters();
		self::assertCount( 1, $delegated );
		self::assertSame( 'the_content', $delegated[0]['hook'] );
		self::assertSame( 5, $delegated[0]['priority'] );
		self::assertSame( 2, $delegated[0]['accepted_args'] );
	}

	public function test_get_buffer_returns_underlying_handler(): void {
		$buffer  = new BufferedHookHandler( 'inner', new HookRegistry() );
		$handler = new ScopedHookHandler( 'admin', 'admin_init', '', $buffer );

		self::assertSame( $buffer, $handler->get_buffer() );
	}
}
