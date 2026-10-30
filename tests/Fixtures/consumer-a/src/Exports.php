<?php declare( strict_types=1 );

namespace Acme\ConsumerA;

use Acme\ConsumerA\Scoped\DeepWebSolutions\Framework\Core\ComponentInterface;
use Acme\ConsumerA\Scoped\DeepWebSolutions\Framework\Shared\Result\AbstractResult;
use Acme\ConsumerA\Scoped\DeepWebSolutions\Framework\Shared\Result\Failure;
use Acme\ConsumerA\Scoped\DeepWebSolutions\Framework\Shared\Result\Success;

\defined( 'ABSPATH' ) || exit;

/**
 * Sends the store's order count to shop managers, relaxing the export safeguard for that request's count only.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final class Exports implements ComponentInterface {
	// region FIELDS AND CONSTANTS

	/**
	 * The admin-post action that sends the order count, which is also its nonce action.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     string
	 */
	public const string ACTION = Plugin::PREFIX . '_export';

	// endregion

	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	#[\Override]
	public function register_hooks(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'send_order_count' ) );
	}

	// endregion

	// region HOOKS

	/**
	 * Sends the order count as JSON to a user who manages WooCommerce, with the export safeguard relaxed while it counts.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function send_order_count(): void {
		check_admin_referer( self::ACTION );
		if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce registers the capability.
			wp_die( esc_html__( 'Only shop managers can export orders.', 'acme-consumer-a' ), 403 );
		}

		add_filter( Plugin::PREFIX . '/exports/should_restrict', '__return_false' );
		try {
			$count = $this->count_orders();
		} finally {
			remove_filter( Plugin::PREFIX . '/exports/should_restrict', '__return_false' );
		}

		if ( $count->is_success() ) {
			wp_send_json_success( array( 'orders' => $count->value ) );
		}

		wp_send_json_error( array( 'reason' => $count->error->value ), 409 );
	}

	// endregion

	// region HELPERS

	/**
	 * Returns the store's order count, or why it could not be counted.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  AbstractResult<int, ExportError>
	 */
	#[\NoDiscard]
	protected function count_orders(): AbstractResult {
		if ( ! current_user_can( Plugin::PREFIX . '_export_orders' ) ) {
			return Failure::from( ExportError::Locked );
		}

		$orders = wc_get_orders(
			array(
				'limit'    => 1,
				'paginate' => true,
				'return'   => 'ids',
			)
		);

		return \is_object( $orders ) && \is_int( $orders->total ?? null ) ? Success::from( $orders->total ) : Failure::from( ExportError::QueryFailed );
	}

	// endregion
}
