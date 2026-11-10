<?php declare( strict_types=1 );
/**
 * Returns the php-scoper config that prefixes the shared package with the project's namespace.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @package Acme\PersonaShared
 */

return ( require __DIR__ . '/vendor/ahegyes/wordpress-configs/php/php-scoper/scoper-base.inc.php' )( __DIR__, 'ahegyes/wp-framework-*' );
