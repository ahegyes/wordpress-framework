<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Shared\Result;

/**
 * A successful outcome carrying its value.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @template-covariant TValue
 * @extends AbstractResult<TValue, never>
 */
final readonly class Success extends AbstractResult {
	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   TValue $value The operation's value.
	 */
	protected function __construct(
		public mixed $value
	) {}

	// endregion

	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function is_success(): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function match( callable $on_success, callable $on_failure ): mixed {
		return $on_success( $this->value );
	}

	// endregion

	// region FACTORY METHODS

	/**
	 * Wraps a value in a success.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @template T
	 *
	 * @param   T $value The operation's value.
	 *
	 * @return  self<T>
	 */
	public static function from( mixed $value ): self {
		return new self( $value );
	}

	// endregion
}
