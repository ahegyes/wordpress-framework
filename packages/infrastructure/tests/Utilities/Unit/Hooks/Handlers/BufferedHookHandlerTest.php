<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Unit\Hooks\Handlers;

use DeepWebSolutions\Framework\Utilities\Hooks\Handlers\BufferedHookHandler;
use DeepWebSolutions\Framework\Utilities\Hooks\HookRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( BufferedHookHandler::class )]
#[UsesClass( HookRegistry::class )]
final class BufferedHookHandlerTest extends TestCase {
	public function test_default_id_is_buffered(): void {
		$handler = new BufferedHookHandler();

		self::assertSame( 'buffered', BufferedHookHandler::DEFAULT_ID );
		self::assertSame( BufferedHookHandler::DEFAULT_ID, $handler->id );
	}

	public function test_custom_id_is_returned(): void {
		$handler = new BufferedHookHandler( 'admin-only' );

		self::assertSame( 'admin-only', $handler->id );
	}

	public function test_add_action_queues_without_calling_wp(): void {
		$handler = new BufferedHookHandler();
		$cb      = static function (): void {};

		$handler->add_action( 'init', $cb, 10, 1 );

		$queued = $handler->registry->actions;
		self::assertCount( 1, $queued );
		self::assertSame( 'init', $queued[0]['hook'] );
		self::assertSame( 10, $queued[0]['priority'] );
		self::assertSame( 1, $queued[0]['accepted_args'] );
	}

	public function test_add_filter_queues_without_calling_wp(): void {
		$handler = new BufferedHookHandler();
		$cb      = static fn ( $v ) => $v;

		$handler->add_filter( 'the_content', $cb, 5, 2 );

		$queued = $handler->registry->filters;
		self::assertCount( 1, $queued );
		self::assertSame( 'the_content', $queued[0]['hook'] );
		self::assertSame( 5, $queued[0]['priority'] );
		self::assertSame( 2, $queued[0]['accepted_args'] );
	}
}
