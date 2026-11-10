<?php declare( strict_types=1 );
/**
 * Registers the plugin's settings page and post form on plugins_loaded.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @package Acme\PersonaSettings
 */

use Acme\PersonaSettings\Details;
use Acme\PersonaSettings\Settings;
use Acme\PersonaSettings\Scoped\DeepWebSolutions\Framework\Core\AdminNotice\AdminNotices;
use Acme\PersonaSettings\Scoped\DeepWebSolutions\Framework\Core\Logging\ErrorLogLogger;
use Acme\PersonaSettings\Scoped\DeepWebSolutions\Framework\Settings\AdminPage\OptionsPage;
use Acme\PersonaSettings\Scoped\DeepWebSolutions\Framework\Settings\EditScreen\Hosts\PostMetaBox;
use Acme\PersonaSettings\Scoped\DeepWebSolutions\Framework\Settings\EditScreen\ObjectForm;

defined( 'ABSPATH' ) || exit;

add_action( 'plugins_loaded', 'acme_ps_register_hooks', 15 );

/**
 * Registers the hooks of the plugin's settings page, of its post form and of the notices that report the form's rejections.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @return  void
 */
function acme_ps_register_hooks(): void {
	$notices = new AdminNotices( Settings::PREFIX, new ErrorLogLogger( 'acme-persona-settings' ) );
	$notices->register_hooks();

	new OptionsPage( Settings::PREFIX, new Settings() )->register_hooks();
	new ObjectForm( Settings::PREFIX . '_details', new Details(), new PostMetaBox( array( 'post' ) ), $notices )->register_hooks();
}
