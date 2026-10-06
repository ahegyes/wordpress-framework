<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Shared\Result;

use DeepWebSolutions\Framework\Shared\Error\ErrorInterface;

/**
 * A failed outcome carrying its error.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @template-covariant TError of ErrorInterface
 * @extends AbstractResult<never, TError>
 */
final readonly class Failure extends AbstractResult {
	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   TError $error The operation's error.
	 */
	protected function __construct(
		public ErrorInterface $error
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
		return false;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function match( callable $on_success, callable $on_failure ): mixed {
		return $on_failure( $this->error );
	}

	// endregion

	// region FACTORY METHODS

	/**
	 * Wraps an error in a failure.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @template E of ErrorInterface
	 *
	 * @param   E $error The operation's error.
	 *
	 * @return  self<E>
	 */
	public static function from( ErrorInterface $error ): self {
		return new self( $error );
	}

	// endregion
}
