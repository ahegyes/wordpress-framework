<?php declare( strict_types=1 );

namespace Acme\ConsumerA;

use Acme\ConsumerA\Scoped\DeepWebSolutions\Framework\Shared\Error\ErrorInterface;

\defined( 'ABSPATH' ) || exit;

/**
 * The reasons an order export fails.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
enum ExportError: string implements ErrorInterface {
	/**
	 * The export safeguard denies the current user.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	case Locked = 'locked';

	/**
	 * WooCommerce's order query returned no count.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	case QueryFailed = 'query_failed';
}
