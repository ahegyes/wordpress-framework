<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Shared\Tests\Unit\Identifier;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function DeepWebSolutions\Framework\Shared\Identifier\is_valid_global_name_prefix;
use function DeepWebSolutions\Framework\Shared\Identifier\is_valid_identifier;

#[CoversFunction( 'DeepWebSolutions\Framework\Shared\Identifier\is_valid_identifier' )]
#[CoversFunction( 'DeepWebSolutions\Framework\Shared\Identifier\is_valid_global_name_prefix' )]
final class FunctionsTest extends TestCase {
	#[DataProvider( 'valid_identifiers' )]
	public function test_accepts_a_valid_identifier( string $identifier ): void {
		self::assertTrue( is_valid_identifier( $identifier ) );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function valid_identifiers(): array {
		return array(
			'single letter'   => array( 'a' ),
			'word'            => array( 'field' ),
			'with digit'      => array( 'field2' ),
			'with underscore' => array( 'my_field' ),
			'with hyphen'     => array( 'my-field' ),
			'mixed'           => array( 'a1_b-2' ),
		);
	}

	#[DataProvider( 'invalid_identifiers' )]
	public function test_rejects_an_invalid_identifier( string $identifier ): void {
		self::assertFalse( is_valid_identifier( $identifier ) );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function invalid_identifiers(): array {
		return array(
			'empty'              => array( '' ),
			'leading digit'      => array( '1field' ),
			'leading hyphen'     => array( '-field' ),
			'leading underscore' => array( '_field' ),
			'uppercase'          => array( 'Field' ),
			'space'              => array( 'my field' ),
			'dot'                => array( 'my.field' ),
			'slash'              => array( 'my/field' ),
			'trailing newline'   => array( "field\n" ),
		);
	}

	#[DataProvider( 'valid_prefixes' )]
	public function test_accepts_a_valid_global_name_prefix( string $prefix ): void {
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
			'trailing separator' => array( '_dws-wrwc_' ),
		);
	}

	#[DataProvider( 'invalid_prefixes' )]
	public function test_rejects_an_invalid_global_name_prefix( string $prefix ): void {
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
