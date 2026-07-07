<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Unit\Conditionals\Dependencies;

use DeepWebSolutions\Framework\Utilities\Conditionals\Dependencies\PHPIniSizeConditional;
use DeepWebSolutions\Framework\Utilities\Conditionals\Exceptions\InvalidConditionalConfigurationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Only the WP-free constructor validation is exercised here; the live ini-comparison
 * behaviour runs in the integration suite.
 */
#[CoversClass( PHPIniSizeConditional::class )]
final class PHPIniSizeConditionalTest extends TestCase {
	#[DataProvider( 'valid_minimums' )]
	public function test_accepts_integer_byte_shorthand( string $minimum ): void {
		$this->expectNotToPerformAssertions();

		new PHPIniSizeConditional( 'memory_limit', $minimum );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function valid_minimums(): array {
		return array(
			'plain digits'           => array( '512' ),
			'kilo lowercase'         => array( '64k' ),
			'mega uppercase'         => array( '128M' ),
			'giga lowercase'         => array( '1g' ),
			'surrounding whitespace' => array( ' 128M ' ),
		);
	}

	#[DataProvider( 'invalid_minimums' )]
	public function test_rejects_a_malformed_minimum( string $minimum ): void {
		$this->expectException( InvalidConditionalConfigurationException::class );

		new PHPIniSizeConditional( 'memory_limit', $minimum );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function invalid_minimums(): array {
		return array(
			'empty'           => array( '' ),
			'whitespace only' => array( ' ' ),
			'suffix only'     => array( 'M' ),
			'fractional'      => array( '1.5G' ),
			'inner space'     => array( '12 M' ),
			'byte word'       => array( '128MB' ),
			'negative'        => array( '-1' ),
			'double suffix'   => array( '1gg' ),
		);
	}

	public function test_rejects_an_empty_directive_name(): void {
		$this->expectException( InvalidConditionalConfigurationException::class );

		new PHPIniSizeConditional( '', '128M' );
	}

	public function test_rejects_a_whitespace_only_directive_name(): void {
		$this->expectException( InvalidConditionalConfigurationException::class );

		new PHPIniSizeConditional( '   ', '128M' );
	}
}
