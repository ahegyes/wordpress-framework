<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Unit\Caching;

use DeepWebSolutions\Framework\Utilities\Caching\ObjectCache;
use DeepWebSolutions\Framework\Utilities\Exceptions\InvalidGlobalNamePrefixException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesFunction;
use PHPUnit\Framework\TestCase;

/**
 * Only the WP-free constructor guard is exercised here; the live object-cache behaviour runs
 * in the integration suite.
 */
#[CoversClass( ObjectCache::class )]
#[UsesFunction( 'DeepWebSolutions\Framework\Utilities\is_valid_global_name_prefix' )]
final class ObjectCacheTest extends TestCase {
	public function test_rejects_a_group_outside_the_global_name_charset(): void {
		$this->expectException( InvalidGlobalNamePrefixException::class );

		new ObjectCache( 'dws.bad.group' );
	}

	public function test_accepts_a_valid_group(): void {
		$this->expectNotToPerformAssertions();

		new ObjectCache( 'dws_oc_test' );
	}
}
