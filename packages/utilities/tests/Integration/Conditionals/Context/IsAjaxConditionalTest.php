<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Integration\Conditionals\Context;

use DeepWebSolutions\Framework\Utilities\Conditionals\Context\IsAjaxConditional;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( IsAjaxConditional::class )]
final class IsAjaxConditionalTest extends TestCase {
	public function test_returns_false_outside_ajax(): void {
		self::assertFalse( ( new IsAjaxConditional() )->is_met() );
	}
}
