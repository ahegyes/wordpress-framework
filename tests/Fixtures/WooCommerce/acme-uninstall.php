<?php declare( strict_types=1 );
/**
 * Runs the request spec's uninstall on admin_init with WooCommerce left out of the active plugins, as when a plugin is deleted after WooCommerce was deactivated.
 *
 * @package Acme
 */

use DeepWebSolutions\Framework\Core\Uninstall\Uninstaller;
use DeepWebSolutions\Framework\WooCommerce\Uninstall\MetaTypes;

require_once dirname( __DIR__, 3 ) . '/vendor/autoload.php';

add_filter( 'option_active_plugins', static fn ( array $plugins ): array => array_values( array_diff( $plugins, array( 'woocommerce/woocommerce.php' ) ) ) );

add_action(
	'admin_init',
	static function () use ( $spec ): void {
		$GLOBALS['acme_outcome']['records']['woocommerce loaded'] = class_exists( 'WooCommerce', false );
		Uninstaller::run(
			$spec['plugin_file'],
			$spec['footprint'],
			array(
				'consent'    => $spec['consent'],
				'meta_types' => MetaTypes::DEFINITIONS,
			)
		);
	}
);
