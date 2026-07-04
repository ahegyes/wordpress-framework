<?php declare( strict_types=1 );
/**
 * Second-consumer php-scoper config — framework packages plus the scoped PHP-DI
 * every consumer carries; the coexistence smoke needs a second scoped copy of both.
 *
 * @package DeepWebSolutions\Framework\Tests\ConsumerSmokeB
 */

$base_config_factory = require __DIR__ . '/vendor/ahegyes/wordpress-configs/php/php-scoper/scoper-base.inc.php';
$php_di_partial      = ( require __DIR__ . '/vendor/ahegyes/wordpress-configs/php/php-scoper/contrib/php-di.inc.php' )( __DIR__ . '/vendor' );
$wp_framework        = ( require __DIR__ . '/vendor/ahegyes/wordpress-configs/php/php-scoper/contrib/wp-framework.inc.php' )( __DIR__ . '/vendor', __DIR__ );

return $base_config_factory(
	array(
		'project_dir'   => __DIR__,
		'finders'       => array_merge( $wp_framework['finders'], $php_di_partial['finders'] ),
		'exclude_files' => $php_di_partial['exclude_files'],
		'patchers'      => $wp_framework['patchers'],
	)
);
