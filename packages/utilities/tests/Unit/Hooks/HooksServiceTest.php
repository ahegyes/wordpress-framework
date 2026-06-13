<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Unit\Hooks;

use DeepWebSolutions\Framework\Utilities\Hooks\Contracts\HookHandlerInterface;
use DeepWebSolutions\Framework\Utilities\Hooks\Handlers\DirectHookHandler;
use DeepWebSolutions\Framework\Utilities\Hooks\HooksService;
use OutOfBoundsException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( HooksService::class )]
#[UsesClass( DirectHookHandler::class )]
final class HooksServiceTest extends TestCase {
	public function test_constructs_with_default_direct_handler(): void {
		$service = new HooksService();

		self::assertInstanceOf( DirectHookHandler::class, $service->get_handler( 'direct' ) );
	}

	public function test_empty_array_yields_zero_handlers(): void {
		$service = new HooksService( array() );

		self::assertSame( array(), $service->handlers );
	}

	public function test_register_handler_indexes_by_id(): void {
		$service = new HooksService( array() );
		$handler = $this->recording_handler( 'buffered' );

		$service->register_handler( $handler );

		self::assertSame( $handler, $service->get_handler( 'buffered' ) );
	}

	public function test_handlers_returns_all(): void {
		$direct   = $this->recording_handler( 'direct' );
		$buffered = $this->recording_handler( 'buffered' );

		$service = new HooksService( array( $direct, $buffered ) );

		self::assertSame(
			array(
				'direct'   => $direct,
				'buffered' => $buffered,
			),
			$service->handlers,
		);
	}

	public function test_get_handler_returns_null_when_not_found(): void {
		$service = new HooksService( array( $this->recording_handler( 'direct' ) ) );

		self::assertNull( $service->get_handler( 'missing' ) );
	}

	public function test_add_action_routes_to_default_handler(): void {
		$direct  = $this->recording_handler( 'direct' );
		$service = new HooksService( array( $direct ) );
		$cb      = static function (): void {};

		$service->add_action( 'init', $cb, 20, 2 );

		self::assertSame(
			array( array( 'add_action', 'init', $cb, 20, 2 ) ),
			$direct->calls,
		);
	}

	public function test_add_action_routes_to_named_handler(): void {
		$direct   = $this->recording_handler( 'direct' );
		$buffered = $this->recording_handler( 'buffered' );
		$service  = new HooksService( array( $direct, $buffered ) );
		$cb       = static function (): void {};

		$service->add_action( 'init', $cb, 10, 1, 'buffered' );

		self::assertSame( array(), $direct->calls );
		self::assertSame(
			array( array( 'add_action', 'init', $cb, 10, 1 ) ),
			$buffered->calls,
		);
	}

	public function test_add_filter_routes_to_named_handler(): void {
		$direct   = $this->recording_handler( 'direct' );
		$buffered = $this->recording_handler( 'buffered' );
		$service  = new HooksService( array( $direct, $buffered ) );
		$cb       = static fn ( $v ) => $v;

		$service->add_filter( 'the_content', $cb, 10, 1, 'buffered' );

		self::assertSame(
			array( array( 'add_filter', 'the_content', $cb, 10, 1 ) ),
			$buffered->calls,
		);
	}

	public function test_remove_action_returns_handler_result(): void {
		$direct  = $this->recording_handler( 'direct', true );
		$service = new HooksService( array( $direct ) );
		$cb      = static function (): void {};

		self::assertTrue( $service->remove_action( 'init', $cb, 10 ) );
	}

	public function test_remove_filter_returns_handler_result(): void {
		$direct  = $this->recording_handler( 'direct', false );
		$service = new HooksService( array( $direct ) );
		$cb      = static fn ( $v ) => $v;

		self::assertFalse( $service->remove_filter( 'the_title', $cb, 10 ) );
	}

	public function test_remove_all_actions_routes_to_named_handler(): void {
		$direct   = $this->recording_handler( 'direct' );
		$buffered = $this->recording_handler( 'buffered' );
		$service  = new HooksService( array( $direct, $buffered ) );

		$service->remove_all_actions( 'buffered' );

		self::assertSame( array( array( 'remove_all_actions' ) ), $buffered->calls );
		self::assertSame( array(), $direct->calls );
	}

	public function test_remove_all_filters_routes_to_named_handler(): void {
		$direct  = $this->recording_handler( 'direct' );
		$service = new HooksService( array( $direct ) );

		$service->remove_all_filters();

		self::assertSame( array( array( 'remove_all_filters' ) ), $direct->calls );
	}

	public function test_unknown_handler_id_throws(): void {
		$service = new HooksService( array( $this->recording_handler( 'direct' ) ) );

		$this->expectException( OutOfBoundsException::class );
		$service->add_action( 'init', static function (): void {}, 10, 1, 'nonexistent' );
	}

	public function test_add_action_defaults_priority_and_accepted_args(): void {
		$direct  = $this->recording_handler( 'direct' );
		$service = new HooksService( array( $direct ) );
		$cb      = static function (): void {};

		$service->add_action( 'init', $cb );

		self::assertSame( array( array( 'add_action', 'init', $cb, 10, 1 ) ), $direct->calls );
	}

	public function test_add_filter_defaults_priority_and_accepted_args(): void {
		$direct  = $this->recording_handler( 'direct' );
		$service = new HooksService( array( $direct ) );
		$cb      = static fn ( $v ) => $v;

		$service->add_filter( 'the_content', $cb );

		self::assertSame( array( array( 'add_filter', 'the_content', $cb, 10, 1 ) ), $direct->calls );
	}

	public function test_remove_action_defaults_priority(): void {
		$direct  = $this->recording_handler( 'direct', true );
		$service = new HooksService( array( $direct ) );
		$cb      = static function (): void {};

		$service->remove_action( 'init', $cb );

		self::assertSame( array( array( 'remove_action', 'init', $cb, 10 ) ), $direct->calls );
	}

	public function test_remove_filter_defaults_priority(): void {
		$direct  = $this->recording_handler( 'direct' );
		$service = new HooksService( array( $direct ) );
		$cb      = static fn ( $v ) => $v;

		$service->remove_filter( 'the_title', $cb );

		self::assertSame( array( array( 'remove_filter', 'the_title', $cb, 10 ) ), $direct->calls );
	}

	/**
	 * @return HookHandlerInterface&object{calls: list<array<int, mixed>>, next_remove: bool}
	 */
	private function recording_handler( string $id, bool $next_remove = false ): HookHandlerInterface {
		return new class( $id, $next_remove ) implements HookHandlerInterface {
			/** @var list<array<int, mixed>> */
			public array $calls = array();

			public function __construct(
				private readonly string $id,
				public readonly bool $next_remove = false,
			) {}

			public function get_id(): string {
				return $this->id;
			}

			public function add_action( string $hook, callable $callback, int $priority, int $accepted_args ): void {
				$this->calls[] = array( 'add_action', $hook, $callback, $priority, $accepted_args );
			}

			public function add_filter( string $hook, callable $callback, int $priority, int $accepted_args ): void {
				$this->calls[] = array( 'add_filter', $hook, $callback, $priority, $accepted_args );
			}

			public function remove_action( string $hook, callable $callback, int $priority ): bool {
				$this->calls[] = array( 'remove_action', $hook, $callback, $priority );
				return $this->next_remove;
			}

			public function remove_filter( string $hook, callable $callback, int $priority ): bool {
				$this->calls[] = array( 'remove_filter', $hook, $callback, $priority );
				return $this->next_remove;
			}

			public function remove_all_actions(): void {
				$this->calls[] = array( 'remove_all_actions' );
			}

			public function remove_all_filters(): void {
				$this->calls[] = array( 'remove_all_filters' );
			}
		};
	}
}
