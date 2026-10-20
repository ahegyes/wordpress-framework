<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Shared\Caching;

/**
 * Names the current generation of a cache's entries, so that starting a new one invalidates every entry stored under an earlier one.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
interface CacheGenerationInterface {
	/**
	 * Returns the current generation, or null when none has started.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  string|null
	 */
	public function get(): ?string;

	/**
	 * Starts a generation that no earlier one equals and returns it, or null when it cannot be stored.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  string|null
	 */
	public function advance(): ?string;
}
