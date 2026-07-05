<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Integration\Conditionals\Context;

use DeepWebSolutions\Framework\Utilities\Conditionals\Context\IsAjaxConditional;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( IsAjaxConditional::class )]
final class IsAjaxConditionalTest extends TestCase {
	protected function tearDown(): void {
		\remove_filter( 'wp_doing_ajax', '__return_true' );
		parent::tearDown();
	}

	public function test_returns_false_outside_ajax(): void {
		self::assertFalse( ( new IsAjaxConditional() )->is_met() );
	}

	public function test_returns_true_during_ajax(): void {
		\add_filter( 'wp_doing_ajax', '__return_true' );

		self::assertTrue( ( new IsAjaxConditional() )->is_met() );
	}
}
