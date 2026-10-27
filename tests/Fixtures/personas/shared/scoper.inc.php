<?php declare( strict_types=1 );
/**
 * Returns the php-scoper config that prefixes the shared package with the project's namespace.
 *
 * @package Acme\PersonaShared
 */

return ( require __DIR__ . '/vendor/ahegyes/wordpress-configs/php/php-scoper/scoper-base.inc.php' )( __DIR__, 'ahegyes/wp-framework-*' );
