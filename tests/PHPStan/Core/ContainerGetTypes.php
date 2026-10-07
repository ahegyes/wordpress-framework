<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\PHPStan\Core;

use DeepWebSolutions\Framework\ComponentInterface;
use DeepWebSolutions\Framework\PluginKernel;
use Psr\Container\ContainerInterface;

use function DeepWebSolutions\Framework\container_get;
use function PHPStan\Testing\assertType;

final class ContainerGetTypes {
	public static function infer_the_entry_type( ContainerInterface $container ): void {
		assertType( 'DeepWebSolutions\Framework\PluginKernel', container_get( $container, PluginKernel::class ) );
	}

	/** @param class-string<ComponentInterface> $class_name */
	public static function infer_the_entry_type_from_a_class_string( ContainerInterface $container, string $class_name ): void {
		assertType( 'DeepWebSolutions\Framework\ComponentInterface', container_get( $container, $class_name ) );
	}
}
