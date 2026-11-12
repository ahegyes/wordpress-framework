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
use Acme\PersonaSettings\Scoped\DeepWebSolutions\Framework\Core\PluginIdentity;
use Acme\PersonaSettings\Scoped\DeepWebSolutions\Framework\Settings\AdminPage\OptionsPage;
use Acme\PersonaSettings\Scoped\DeepWebSolutions\Framework\Settings\EditScreen\Hosts\PostMetaBox;
use Acme\PersonaSettings\Scoped\DeepWebSolutions\Framework\Settings\EditScreen\ObjectForm;

defined( 'ABSPATH' ) || exit;

add_action(
	'plugins_loaded',
	static function (): void {
		$identity = new PluginIdentity( ACME_PS_FILE, Settings::SLUG, Settings::PREFIX );
		$notices  = new AdminNotices( $identity, new ErrorLogLogger( $identity ) );
		$notices->register_hooks();

		new OptionsPage( $identity, Settings::PREFIX, new Settings() )->register_hooks();
		new ObjectForm( $identity, Settings::PREFIX . '_details', new Details(), new PostMetaBox( array( 'post' ) ), $notices )->register_hooks();
	},
	15
);
