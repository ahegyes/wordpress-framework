<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Shared\Tests\Unit\Result;

use DeepWebSolutions\Framework\Shared\Error\ErrorInterface;
use DeepWebSolutions\Framework\Shared\Result\Failure;
use DeepWebSolutions\Framework\Shared\Result\Success;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( Success::class )]
#[CoversClass( Failure::class )]
final class ResultTest extends TestCase {
	public function test_success_carries_its_value(): void {
		$result = Success::from( 42 );
		self::assertSame( 42, $result->value );
	}

	public function test_success_reports_is_success_true(): void {
		$result = Success::from( 'ok' );
		self::assertTrue( $result->is_success() );
		self::assertFalse( $result->is_failure() );
	}

	public function test_success_match_invokes_on_success_callback(): void {
		$result = Success::from( 'value' );
		$output = $result->match(
			static fn ( $value ) => 'success:' . $value,
			static fn ( $error ) => 'failure',
		);
		self::assertSame( 'success:value', $output );
	}

	public function test_failure_carries_its_error(): void {
		$error  = new class() implements ErrorInterface {};
		$result = Failure::from( $error );
		self::assertSame( $error, $result->error );
	}

	public function test_failure_reports_is_success_false(): void {
		$error  = new class() implements ErrorInterface {};
		$result = Failure::from( $error );
		self::assertFalse( $result->is_success() );
		self::assertTrue( $result->is_failure() );
	}

	public function test_failure_match_invokes_on_failure_callback(): void {
		$error  = new class() implements ErrorInterface {};
		$result = Failure::from( $error );
		$output = $result->match(
			static fn ( $value ) => 'success',
			static fn ( $err ) => $err === $error ? 'failure:matched' : 'failure:mismatched',
		);
		self::assertSame( 'failure:matched', $output );
	}
}
