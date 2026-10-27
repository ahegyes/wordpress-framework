<?php declare( strict_types=1 );

namespace Acme\ConsumerA;

use Acme\ConsumerA\Scoped\DeepWebSolutions\Framework\Core\ComponentInterface;
use Acme\ConsumerA\Scoped\DeepWebSolutions\Framework\Core\ConditionalComponentInterface;

\defined( 'ABSPATH' ) || exit;

/**
 * Fails the boot on purpose where the site defines ACME_CA_FAULT, as construct or register, so the framework's tests can observe a failed boot.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final class FaultInjector implements ComponentInterface, ConditionalComponentInterface {
	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @throws  \RuntimeException Thrown when the fault is construct.
	 */
	public function __construct() {
		if ( 'construct' === \constant( 'ACME_CA_FAULT' ) ) {
			throw new \RuntimeException( 'The fault injector failed its construction.' );
		}
	}

	// endregion

	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	#[\Override]
	public static function should_load(): bool {
		return \defined( 'ACME_CA_FAULT' );
	}

	/**
	 * Relaxes the export safeguard, then throws when the fault is register, as a component that fails after hooking a relaxation.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @throws  \RuntimeException Thrown when the fault is register.
	 *
	 * @return  void
	 */
	#[\Override]
	public function register_hooks(): void {
		add_filter( Plugin::PREFIX . '_restrict_exports', '__return_false' );
		if ( 'register' === \constant( 'ACME_CA_FAULT' ) ) {
			throw new \RuntimeException( 'The fault injector failed its hook registration.' );
		}
	}

	// endregion
}
