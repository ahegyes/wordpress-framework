<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Unit\Shared\Result;

use DeepWebSolutions\Framework\Shared\Error\ErrorInterface;
use DeepWebSolutions\Framework\Shared\Result\AbstractResult;
use DeepWebSolutions\Framework\Shared\Result\Failure;
use DeepWebSolutions\Framework\Shared\Result\Success;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

enum Reason implements ErrorInterface {
	case Expired;
}

#[CoversClass( AbstractResult::class )]
#[CoversClass( Failure::class )]
#[CoversClass( Success::class )]
final class ResultTest extends TestCase {
	public static function outcomes(): array {
		return array(
			'success' => array( Success::from( 5 ), 'success', 5 ),
			'failure' => array( Failure::from( Reason::Expired ), 'failure', Reason::Expired ),
		);
	}

	#[DataProvider( 'outcomes' )]
	public function test_match_calls_only_the_callback_of_its_outcome( AbstractResult $result, string $outcome, mixed $payload ): void {
		$calls = array();

		$returned = $result->match(
			static function ( mixed $value ) use ( &$calls ): string {
				$calls[] = array( 'success', $value );
				return 'success';
			},
			static function ( mixed $error ) use ( &$calls ): string {
				$calls[] = array( 'failure', $error );
				return 'failure';
			}
		);

		self::assertSame( $outcome, $returned );
		self::assertSame( array( array( $outcome, $payload ) ), $calls );
	}

	#[DataProvider( 'outcomes' )]
	public function test_the_predicates_agree_with_the_outcome( AbstractResult $result, string $outcome, mixed $payload ): void {
		self::assertSame( 'success' === $outcome, $result->is_success() );
		self::assertSame( 'failure' === $outcome, $result->is_failure() );
	}
}
