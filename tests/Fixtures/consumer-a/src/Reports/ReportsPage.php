<?php declare( strict_types=1 );

namespace Acme\ConsumerA\Reports;

use Acme\ConsumerA\Scoped\DeepWebSolutions\Framework\Core\ComponentInterface;

\defined( 'ABSPATH' ) || exit;

/**
 * Adds the reports page under WooCommerce's menu.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final class ReportsPage implements ComponentInterface {
	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	#[\Override]
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
	}

	// endregion

	// region HOOKS

	/**
	 * Adds the page for users who manage WooCommerce.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function add_page(): void {
		add_submenu_page( 'woocommerce', __( 'Acme reports', 'acme-consumer-a' ), __( 'Acme reports', 'acme-consumer-a' ), 'manage_woocommerce', 'acme-ca-reports', array( $this, 'render_page' ) ); // phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce registers the capability.
	}

	/**
	 * Prints the page.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function render_page(): void {
		echo '<div class="wrap"><h1>' . esc_html__( 'Acme reports', 'acme-consumer-a' ) . '</h1></div>';
	}

	// endregion
}
