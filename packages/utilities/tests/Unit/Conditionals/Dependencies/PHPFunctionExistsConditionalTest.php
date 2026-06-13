<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Unit\Conditionals\Dependencies;

use DeepWebSolutions\Framework\Utilities\Conditionals\Dependencies\PHPFunctionExistsConditional;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( PHPFunctionExistsConditional::class )]
final class PHPFunctionExistsConditionalTest extends TestCase {
	public function test_existing_function_returns_true(): void {
		self::assertTrue( ( new PHPFunctionExistsConditional( 'strlen' ) )->is_met() );
	}

	public function test_missing_function_returns_false(): void {
		self::assertFalse( ( new PHPFunctionExistsConditional( '__dws_definitely_not_real__' ) )->is_met() );
	}
}
