<?php declare( strict_types=1 );
/**
 * Defines the function through which another plugin asks whether the reports are available.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @package Acme\ConsumerA
 */

use Acme\ConsumerA\Reports\Reports;

defined( 'ABSPATH' ) || exit;

/**
 * Returns whether the reports are available on the site.
 *
 * It answers as the reports' gate does, which the plugin evaluates once, at boot, so a setting written later in the same request can make the two differ. It does not tell whether the plugin booted, which the acme_consumer_a/boot/succeeded action announces.
 *
 * @api
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @return  bool
 */
function acme_ca_are_reports_available(): bool {
	return Reports::should_load();
}
