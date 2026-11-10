<?php declare( strict_types=1 );

namespace Acme\PersonaShared;

use Acme\PersonaShared\Scoped\DeepWebSolutions\Framework\Shared\Result\AbstractResult;
use Acme\PersonaShared\Scoped\DeepWebSolutions\Framework\Shared\Result\Failure;
use Acme\PersonaShared\Scoped\DeepWebSolutions\Framework\Shared\Result\Success;

/**
 * Looks values up by their keys.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final readonly class Catalog {
	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   array<string, string> $values The values, keyed by their keys.
	 */
	public function __construct(
		public array $values
	) {}

	// endregion

	// region METHODS

	/**
	 * Returns the value the key holds, or the failure that names the key.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string $key The key.
	 *
	 * @return  AbstractResult<string, UnknownKey>
	 */
	#[\NoDiscard]
	public function find( string $key ): AbstractResult {
		return isset( $this->values[ $key ] ) ? Success::from( $this->values[ $key ] ) : Failure::from( new UnknownKey( $key ) );
	}

	/**
	 * Returns what a lookup of the key found, in words.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   string $key The key.
	 *
	 * @return  string
	 */
	public function describe( string $key ): string {
		$found = $this->find( $key );

		return $found->is_success() ? "found $found->value" : "unknown {$found->error->key}";
	}

	// endregion
}
