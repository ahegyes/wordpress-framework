<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Unit\Hooks;

use DeepWebSolutions\Framework\Utilities\Hooks\HookRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( HookRegistry::class )]
final class HookRegistryTest extends TestCase {
	public function test_starts_empty(): void {
		$registry = new HookRegistry();

		self::assertSame( array(), $registry->get_actions() );
		self::assertSame( array(), $registry->get_filters() );
	}

	public function test_record_action_appends(): void {
		$registry = new HookRegistry();
		$cb       = static function (): void {};

		$registry->record_action( 'init', $cb, 10, 1 );
		$registry->record_action( 'admin_init', $cb, 20, 2 );

		$actions = $registry->get_actions();
		self::assertCount( 2, $actions );
		self::assertSame( 'init', $actions[0]['hook'] );
		self::assertSame( 'admin_init', $actions[1]['hook'] );
		self::assertSame( 20, $actions[1]['priority'] );
		self::assertSame( 2, $actions[1]['accepted_args'] );
	}

	public function test_record_filter_appends(): void {
		$registry = new HookRegistry();
		$cb       = static fn ( $value ) => $value;

		$registry->record_filter( 'the_content', $cb, 5, 1 );

		self::assertCount( 1, $registry->get_filters() );
		self::assertSame( 'the_content', $registry->get_filters()[0]['hook'] );
	}

	public function test_forget_action_removes_first_match(): void {
		$registry = new HookRegistry();
		$cb       = static function (): void {};

		$registry->record_action( 'init', $cb, 10, 1 );
		$registry->record_action( 'admin_init', $cb, 10, 1 );

		self::assertTrue( $registry->forget_action( 'init', $cb, 10 ) );

		$actions = $registry->get_actions();
		self::assertCount( 1, $actions );
		self::assertSame( 'admin_init', $actions[0]['hook'] );
	}

	public function test_forget_action_returns_false_when_no_match(): void {
		$registry = new HookRegistry();
		$cb       = static function (): void {};

		$registry->record_action( 'init', $cb, 10, 1 );

		self::assertFalse( $registry->forget_action( 'shutdown', $cb, 10 ) );
		self::assertCount( 1, $registry->get_actions() );
	}

	public function test_forget_filter_removes_first_match(): void {
		$registry = new HookRegistry();
		$cb       = static fn ( $v ) => $v;

		$registry->record_filter( 'the_title', $cb, 10, 1 );

		self::assertTrue( $registry->forget_filter( 'the_title', $cb, 10 ) );
		self::assertSame( array(), $registry->get_filters() );
	}

	public function test_clear_actions_empties_actions_only(): void {
		$registry = new HookRegistry();
		$cb       = static function (): void {};

		$registry->record_action( 'init', $cb, 10, 1 );
		$registry->record_filter( 'the_content', static fn ( $v ) => $v, 10, 1 );

		$registry->clear_actions();

		self::assertSame( array(), $registry->get_actions() );
		self::assertCount( 1, $registry->get_filters() );
	}

	public function test_clear_filters_empties_filters_only(): void {
		$registry = new HookRegistry();
		$cb       = static function (): void {};

		$registry->record_action( 'init', $cb, 10, 1 );
		$registry->record_filter( 'the_content', static fn ( $v ) => $v, 10, 1 );

		$registry->clear_filters();

		self::assertCount( 1, $registry->get_actions() );
		self::assertSame( array(), $registry->get_filters() );
	}
}
