<?php
/**
 * Smoke-fixture php-scoper config — composes wordpress-configs' scoper-base
 * with the reusable PHP-DI partial. Mirrors the plugin template's pattern.
 *
 * @package DeepWebSolutions\Framework\Tests\ConsumerSmoke
 */

declare( strict_types=1 );

use Isolated\Symfony\Component\Finder\Finder;

$base_config_factory = require __DIR__ . '/vendor/ahegyes/wordpress-configs/php/php-scoper/scoper-base.inc.php';
$php_di_partial      = ( require __DIR__ . '/vendor/ahegyes/wordpress-configs/php/php-scoper/contrib/php-di.inc.php' )( __DIR__ . '/vendor' );

$framework_finders = array(
	Finder::create()
		->files()
		->ignoreVCS( true )
		->name( '*.php' )
		->in( __DIR__ . '/vendor/ahegyes/wp-framework-bootstrap' )
		->in( __DIR__ . '/vendor/ahegyes/wp-framework-core/src' )
		->exclude( array( 'tests', 'Tests' ) ),
);

return $base_config_factory(
	array(
		'project_dir'   => __DIR__,
		'finders'       => array_merge( $framework_finders, $php_di_partial['finders'] ),
		'exclude_files' => $php_di_partial['exclude_files'],
	)
);
