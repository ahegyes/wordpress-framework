<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Shared\Result;

use DeepWebSolutions\Framework\Shared\Error\ErrorInterface;

/**
 * Sealed-type base for an operation outcome — either a {@see Success} or a {@see Failure}.
 *
 * Consumers branch via {@see self::is_success()} / {@see self::is_failure()} or {@see self::match()};
 * after an instanceof or is_failure() check, reach {@see Success::$value} / {@see Failure::$error}
 * directly — no assert() ceremony around the narrowed variant.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @template TValue
 * @template TError of ErrorInterface
 */
abstract readonly class AbstractResult {
	// region MAGIC METHODS

	/**
	 * Protected constructor — variants instantiate through their own factories.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	protected function __construct() {}

	// endregion

	// region METHODS

	/**
	 * Returns whether the result is a success.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  bool
	 */
	abstract public function is_success(): bool;

	/**
	 * Returns whether the result is a failure.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  bool
	 */
	public function is_failure(): bool {
		return ! $this->is_success();
	}

	/**
	 * Branches on the result's variant — invokes `$on_success` for {@see Success},
	 * `$on_failure` for {@see Failure} — and returns the chosen callback's return value.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @template TReturn of mixed
	 *
	 * @param   callable(TValue): TReturn $on_success Success branch.
	 * @param   callable(TError): TReturn $on_failure Failure branch.
	 *
	 * @return  TReturn
	 */
	abstract public function match( callable $on_success, callable $on_failure ): mixed;

	// endregion
}
