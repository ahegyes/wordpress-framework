<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Integration\Conditionals\Context;

use DeepWebSolutions\Framework\Utilities\Conditionals\Context\IsAdminConditional;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( IsAdminConditional::class )]
final class IsAdminConditionalTest extends TestCase {
	public function test_returns_false_outside_admin(): void {
		self::assertFalse( ( new IsAdminConditional() )->is_met() );
	}
}
