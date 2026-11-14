<?php declare( strict_types=1 );
/**
 * Defines the function through which another plugin reads whether the reports are on.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @package Acme\ConsumerA
 */

use Acme\ConsumerA\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Returns whether the site has switched the reports on.
 *
 * It reads the stored setting through Options, as the reports' gate does, and adds no filter. The gate reads it once, at boot, so a write later in the same request can make the two differ, and neither tells whether the reports loaded.
 *
 * @api
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @return  bool
 */
function acme_ca_is_reports_enabled(): bool {
	return Options::get_bool( Options::REPORTS );
}
