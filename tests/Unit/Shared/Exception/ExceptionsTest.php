<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Unit\Shared\Exception;

use DeepWebSolutions\Framework\Shared\Exception\ExceptionInterface;
use DeepWebSolutions\Framework\Shared\Exception\InvalidArgumentException;
use DeepWebSolutions\Framework\Shared\Exception\LogicException;
use DeepWebSolutions\Framework\Shared\Exception\RuntimeException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( InvalidArgumentException::class )]
#[CoversClass( LogicException::class )]
#[CoversClass( RuntimeException::class )]
final class ExceptionsTest extends TestCase {
	public static function mirrors(): array {
		return array(
			'invalid-argument' => array( InvalidArgumentException::class, \InvalidArgumentException::class ),
			'logic'            => array( LogicException::class, \LogicException::class ),
			'runtime'          => array( RuntimeException::class, \RuntimeException::class ),
		);
	}

	#[DataProvider( 'mirrors' )]
	public function test_a_mirror_is_its_spl_parent_and_a_framework_exception( string $mirror, string $spl_parent ): void {
		$exception = new $mirror( 'Failed.' );

		self::assertInstanceOf( $spl_parent, $exception );
		self::assertInstanceOf( ExceptionInterface::class, $exception );
	}
}
