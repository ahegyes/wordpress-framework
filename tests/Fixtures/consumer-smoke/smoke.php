<?php declare( strict_types=1 );
/**
 * Smoke run — exercises every framework symbol the consumer contract exposes,
 * forcing autoload to resolve scoped classes/functions across all three sources
 * (bootstrap files-autoload, core PSR-4, PHP-DI PSR-4 + transitive deps).
 *
 * Failure modes this catches:
 * - Bootstrap's `files`-autoloaded check-requirements.php missing or mis-pathed
 *   in dependencies/.
 * - Core's PluginKernel mis-pathed under PSR-4 (autoload metadata drift).
 * - PHP-DI's ContainerBuilder mis-pathed (contrib finder/exclude_files
 *   regression).
 * - Scoped types referencing global PSR (e.g. ContainerInterface) prefixed
 *   when they shouldn't be.
 *
 * @package DeepWebSolutions\Framework\Tests\ConsumerSmoke
 */

require __DIR__ . '/vendor/autoload.php';

$is_php_compatible = \DWS_CONSUMER_SMOKE_Deps\DeepWebSolutions\Framework\Bootstrap\is_php_compatible( '5.6' );
if ( ! is_bool( $is_php_compatible ) ) {
	fwrite( STDERR, "is_php_compatible returned non-bool\n" );
	exit( 1 );
}

$container = new class () implements \Psr\Container\ContainerInterface {
	public function get( string $id ): mixed {
		throw new \LogicException( 'smoke fixture: container::get not exercised' );
	}
	public function has( string $id ): bool {
		return false;
	}
};
$kernel = new \DWS_CONSUMER_SMOKE_Deps\DeepWebSolutions\Framework\Core\Kernel\PluginKernel( $container );

$di_builder = new \DWS_CONSUMER_SMOKE_Deps\DI\ContainerBuilder();

$factory_def = \DWS_CONSUMER_SMOKE_Deps\DI\factory(
	static fn(): string => 'smoke'
);

echo "OK\n";
