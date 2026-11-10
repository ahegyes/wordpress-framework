<?php declare( strict_types=1 );
/**
 * Returns the php-scoper config that prefixes the bootstrap package with the plugin's namespace.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @package Acme\PersonaBootstrap
 */

return ( require __DIR__ . '/vendor/ahegyes/wordpress-configs/php/php-scoper/scoper-base.inc.php' )( __DIR__, 'ahegyes/wp-framework-*' );
