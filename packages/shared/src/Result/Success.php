<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Shared\Result;

/**
 * Successful variant of {@see AbstractResult} — carries the operation's return value.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @template TValue
 * @extends AbstractResult<TValue, never>
 */
final class Success extends AbstractResult {
	// region MAGIC METHODS

	/**
	 * Private constructor — use {@see self::from()}.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed $value Return value carried by the success.
	 */
	protected function __construct(
		public readonly mixed $value
	) {
		parent::__construct();
	}

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
	public function match( callable $on_success, callable $on_failure ): mixed { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		return $on_success( $this->value );
	}

	// endregion

	// region METHODS

	/**
	 * Creates a new successful result wrapping the given return value.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed $value The operation's return value.
	 *
	 * @return  self
	 *
	 * @phpstan-ignore missingType.generics
	 */
	public static function from( mixed $value ): self {
		return new self( $value );
	}

	// endregion
}
