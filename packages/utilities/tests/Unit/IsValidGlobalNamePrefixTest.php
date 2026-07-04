<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function DeepWebSolutions\Framework\Utilities\is_valid_global_name_prefix;

#[CoversFunction( 'DeepWebSolutions\Framework\Utilities\is_valid_global_name_prefix' )]
final class IsValidGlobalNamePrefixTest extends TestCase {
	#[DataProvider( 'valid_prefixes' )]
	public function test_accepts_a_valid_prefix( string $prefix ): void {
		self::assertTrue( is_valid_global_name_prefix( $prefix ) );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function valid_prefixes(): array {
		return array(
			'single letter'      => array( 'a' ),
			'word'               => array( 'dws' ),
			'with underscore'    => array( 'dws_cache' ),
			'with hyphen'        => array( 'dws-cache' ),
			'with digits'        => array( 'dws2_cache' ),
			'leading underscore' => array( '_dws_cache' ),
		);
	}

	#[DataProvider( 'invalid_prefixes' )]
	public function test_rejects_an_invalid_prefix( string $prefix ): void {
		self::assertFalse( is_valid_global_name_prefix( $prefix ) );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function invalid_prefixes(): array {
		return array(
			'empty'             => array( '' ),
			'underscore only'   => array( '_' ),
			'double underscore' => array( '__dws' ),
			'leading digit'     => array( '2dws' ),
			'uppercase'         => array( 'Dws' ),
			'space'             => array( 'dws cache' ),
			'dot'               => array( 'dws.cache' ),
			'trailing newline'  => array( "dws\n" ),
			'leading hyphen'    => array( '-dws' ),
		);
	}
}
