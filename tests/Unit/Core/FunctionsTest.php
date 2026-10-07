<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Unit\Core;

use DeepWebSolutions\Framework\Shared\Exception\LogicException;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

use function DeepWebSolutions\Framework\container_get;

#[CoversFunction( 'DeepWebSolutions\Framework\container_get' )]
final class FunctionsTest extends TestCase {
	public function test_container_get_returns_an_entry_of_the_requested_class(): void {
		$entry = new \ArrayObject();

		self::assertSame( $entry, container_get( self::container_holding( $entry ), \ArrayObject::class ) );
	}

	public function test_container_get_throws_for_an_entry_of_another_class(): void {
		$this->expectException( LogicException::class );
		$this->expectExceptionMessage( "'stdClass'" );

		container_get( self::container_holding( new \stdClass() ), \ArrayObject::class );
	}

	protected static function container_holding( object $entry ): ContainerInterface {
		return new class( $entry ) implements ContainerInterface {
			public function __construct(
				protected object $entry
			) {}

			#[\Override]
			public function get( string $id ): mixed {
				return $this->entry;
			}

			#[\Override]
			public function has( string $id ): bool {
				return true;
			}
		};
	}
}
