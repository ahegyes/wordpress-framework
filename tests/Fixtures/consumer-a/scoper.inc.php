<?php declare( strict_types=1 );
/**
 * Returns the php-scoper config that prefixes the framework and PHP-DI with the plugin's namespace.
 *
 * @package Acme\ConsumerA
 */

return ( require __DIR__ . '/vendor/ahegyes/wordpress-configs/php/php-scoper/scoper-base.inc.php' )( __DIR__, 'ahegyes/wp-framework-*', 'php-di/php-di' );
