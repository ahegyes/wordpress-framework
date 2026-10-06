<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\PHPStan\Shared;

use DeepWebSolutions\Framework\Shared\Error\ErrorInterface;
use DeepWebSolutions\Framework\Shared\Result\AbstractResult;
use DeepWebSolutions\Framework\Shared\Result\Failure;
use DeepWebSolutions\Framework\Shared\Result\Success;

use function PHPStan\Testing\assertType;

enum QuoteError implements ErrorInterface {
	case Expired;
	case Missing;
}

enum OrderError implements ErrorInterface {
	case Cancelled;
}

/**
 * @extends AbstractResult<int, QuoteError>
 * @phpstan-ignore class.disallowedSubtype
 */
final readonly class Pending extends AbstractResult {
	#[\Override]
	public function is_success(): bool {
		return false;
	}

	#[\Override]
	public function match( callable $on_success, callable $on_failure ): mixed {
		return $on_failure( QuoteError::Expired );
	}
}

final class ResultTypes {
	/** @return AbstractResult<int, QuoteError> */
	public static function find_quantity( bool $found ): AbstractResult {
		return $found ? Success::from( 5 ) : Failure::from( QuoteError::Missing );
	}

	/** @return AbstractResult<int, QuoteError> */
	public static function find_quantity_with_a_wrong_value(): AbstractResult {
		return Success::from( 'five' ); // @phpstan-ignore return.type
	}

	/** @return AbstractResult<int, QuoteError> */
	public static function find_quantity_with_a_wrong_error(): AbstractResult {
		return Failure::from( OrderError::Cancelled ); // @phpstan-ignore return.type
	}

	public static function infer_the_value_type(): void {
		assertType( 'DeepWebSolutions\Framework\Shared\Result\Success<int>', Success::from( 5 ) );
	}

	public static function narrow_to_the_error_reason( bool $found ): void {
		$result = self::find_quantity( $found );

		if ( $result->is_failure() ) {
			assertType( 'DeepWebSolutions\Framework\Tests\PHPStan\Shared\QuoteError', $result->error );
			return;
		}

		assertType( 'int', $result->value );
	}

	public static function narrow_to_the_value( bool $found ): void {
		$result = self::find_quantity( $found );

		if ( $result->is_success() ) {
			assertType( 'int', $result->value );
			return;
		}

		assertType( 'DeepWebSolutions\Framework\Tests\PHPStan\Shared\QuoteError', $result->error );
	}

	public static function match_both_outcomes( bool $found ): void {
		assertType( "'x'|int", self::find_quantity( $found )->match( static fn ( int $value ): int => $value, static fn ( QuoteError $error ): string => 'x' ) );
	}
}
