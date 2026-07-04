<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Shared\Tests\Unit\ValueObject\Exceptions;

use DeepWebSolutions\Framework\Shared\Exception\AbstractInvalidArgumentException;
use DeepWebSolutions\Framework\Shared\Exception\ExceptionInterface;
use DeepWebSolutions\Framework\Shared\ValueObject\Exceptions\InvalidValueObjectException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

final class FixtureInvalidValueObjectException extends InvalidValueObjectException {
	protected string $value_object_type [ get => 'FixtureValueObject'; ]
}

#[CoversClass( InvalidValueObjectException::class )]
final class InvalidValueObjectExceptionTest extends TestCase {
	public function test_message_includes_value_object_type_and_reason(): void {
		$e = new FixtureInvalidValueObjectException( 'count must be non-negative' );
		self::assertStringContainsString( 'FixtureValueObject', $e->getMessage() );
		self::assertStringContainsString( 'count must be non-negative', $e->getMessage() );
	}

	public function test_extends_invalid_argument_exception_base(): void {
		$e = new FixtureInvalidValueObjectException( 'reason' );
		self::assertInstanceOf( AbstractInvalidArgumentException::class, $e );
		self::assertInstanceOf( ExceptionInterface::class, $e );
		self::assertInstanceOf( \InvalidArgumentException::class, $e );
	}

	public function test_passes_code_through(): void {
		$e = new FixtureInvalidValueObjectException( 'reason', 42 );
		self::assertSame( 42, $e->getCode() );
	}

	public function test_passes_previous_through(): void {
		$previous = new \RuntimeException( 'underlying cause' );
		$e        = new FixtureInvalidValueObjectException( 'reason', 0, $previous );
		self::assertSame( $previous, $e->getPrevious() );
	}
}
