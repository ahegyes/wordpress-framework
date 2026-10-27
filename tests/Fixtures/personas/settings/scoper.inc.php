<?php declare( strict_types=1 );
/**
 * Returns the php-scoper config that prefixes the framework with the plugin's namespace.
 *
 * @package Acme\PersonaSettings
 */

return ( require __DIR__ . '/vendor/ahegyes/wordpress-configs/php/php-scoper/scoper-base.inc.php' )( __DIR__, 'ahegyes/wp-framework-*' );
