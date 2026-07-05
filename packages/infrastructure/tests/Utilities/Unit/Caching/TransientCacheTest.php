<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Unit\Caching;

use DeepWebSolutions\Framework\Utilities\Caching\TransientCache;
use DeepWebSolutions\Framework\Utilities\Exceptions\InvalidGlobalNamePrefixException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesFunction;
use PHPUnit\Framework\TestCase;

/**
 * Only the WP-free constructor guard is exercised here; the live transient behaviour runs
 * in the integration suite.
 */
#[CoversClass( TransientCache::class )]
#[UsesFunction( 'DeepWebSolutions\Framework\Shared\Identifier\is_valid_global_name_prefix' )]
final class TransientCacheTest extends TestCase {
	public function test_rejects_a_key_prefix_outside_the_global_name_charset(): void {
		$this->expectException( InvalidGlobalNamePrefixException::class );

		new TransientCache( 'Dws Bad Prefix' );
	}

	public function test_accepts_a_valid_key_prefix(): void {
		$this->expectNotToPerformAssertions();

		new TransientCache( '_dws_test_cache' );
	}
}
