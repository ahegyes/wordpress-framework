<?php declare( strict_types=1 );
/**
 * Holds the plugin's option keys.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @package Acme\PersonaCore
 */

use Acme\PersonaCore\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * The option that holds the plugin's settings: the greeting, and 'yes' under delete_data where the site consents to uninstall removing the plugin's data.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @var     string
 */
const ACME_PC_OPTION_SETTINGS = Plugin::PREFIX . '_settings';
