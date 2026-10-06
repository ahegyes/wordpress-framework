<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Shared\Result;

use DeepWebSolutions\Framework\Shared\Error\ErrorInterface;

/**
 * The outcome of an operation whose expected failures a caller branches on.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @template-covariant TValue
 * @template-covariant TError of ErrorInterface
 * @phpstan-sealed Success|Failure
 */
abstract readonly class AbstractResult {
	// region METHODS

	/**
	 * Returns whether the operation succeeded.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @phpstan-assert-if-true  Success<TValue> $this
	 * @phpstan-assert-if-false Failure<TError> $this
	 *
	 * @return  bool
	 */
	abstract public function is_success(): bool;

	/**
	 * Returns whether the operation failed.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @phpstan-assert-if-true  Failure<TError> $this
	 * @phpstan-assert-if-false Success<TValue> $this
	 *
	 * @return  bool
	 */
	public function is_failure(): bool {
		return ! $this->is_success();
	}

	/**
	 * Passes the value or the error to the matching callback and returns what it returns.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @template TOnSuccess
	 * @template TOnFailure
	 *
	 * @param   callable(TValue): TOnSuccess $on_success The callback that receives the value of a success.
	 * @param   callable(TError): TOnFailure $on_failure The callback that receives the error of a failure.
	 *
	 * @return  TOnSuccess|TOnFailure
	 */
	abstract public function match( callable $on_success, callable $on_failure ): mixed;

	// endregion
}
