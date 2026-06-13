<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Unit\Conditionals\Context;

use DeepWebSolutions\Framework\Utilities\Conditionals\Context\IsCliConditional;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( IsCliConditional::class )]
final class IsCliConditionalTest extends TestCase {
	public function test_returns_true_in_cli_sapi(): void {
		self::assertTrue( ( new IsCliConditional() )->is_met() );
	}
}
