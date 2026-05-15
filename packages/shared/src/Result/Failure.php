<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Shared\Result;

use DeepWebSolutions\Framework\Shared\Error\ErrorInterface;

/**
 * Failed variant of {@see AbstractResult} — carries an {@see ErrorInterface} payload.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @template TError of ErrorInterface
 * @extends AbstractResult<never, TError>
 */
final class Failure extends AbstractResult {
	// region MAGIC METHODS

	/**
	 * Private constructor — use {@see self::from()}.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   ErrorInterface $error Error payload carried by the failure.
	 */
	private function __construct(
		public readonly ErrorInterface $error
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
	public function is_success(): bool {
		return false;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function match( callable $on_success, callable $on_failure ): mixed { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		// @phpstan-ignore-next-line argument.type
		return $on_failure( $this->error );
	}

	// endregion

	// region METHODS

	/**
	 * Creates a new failure result wrapping the given error.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   ErrorInterface $error The error that occurred.
	 *
	 * @return  self
	 *
	 * @phpstan-ignore missingType.generics
	 */
	public static function from( ErrorInterface $error ): self {
		return new self( $error );
	}

	// endregion
}
