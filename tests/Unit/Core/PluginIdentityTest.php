<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Unit\Core;

use DeepWebSolutions\Framework\Core\PluginIdentity;
use DeepWebSolutions\Framework\Shared\Exception\InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( PluginIdentity::class )]
final class PluginIdentityTest extends TestCase {
	public function test_the_hook_root_is_the_slug_with_underscores_for_its_dashes(): void {
		self::assertSame( 'acme_plugin_name', new PluginIdentity( '/plugins/acme/acme.php', 'acme-plugin-name', 'acme' )->hook_root );
	}

	public static function invalid_prefixes(): array {
		return array(
			'empty'            => array( '' ),
			'uppercase'        => array( 'Acme' ),
			'leading-digit'    => array( '1acme' ),
			'hyphen'           => array( 'ac-me' ),
			'trailing-newline' => array( "acme\n" ),
			'33-characters'    => array( 'a' . \str_repeat( 'b', 32 ) ),
		);
	}

	#[DataProvider( 'invalid_prefixes' )]
	public function test_a_prefix_outside_the_pattern_is_rejected( string $prefix ): void {
		$this->expectException( InvalidArgumentException::class );

		new PluginIdentity( '/plugins/acme/acme.php', 'acme', $prefix );
	}

	public function test_a_prefix_may_reach_32_characters(): void {
		$prefix = 'a' . \str_repeat( 'b', 31 );

		self::assertSame( $prefix, new PluginIdentity( '/plugins/acme/acme.php', 'acme', $prefix )->prefix );
	}
}
