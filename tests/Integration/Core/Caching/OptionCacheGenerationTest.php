<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Core\Caching;

use DeepWebSolutions\Framework\Caching\OptionCacheGeneration;
use DeepWebSolutions\Framework\Shared\Exception\InvalidArgumentException;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesStorageTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( OptionCacheGeneration::class )]
final class OptionCacheGenerationTest extends TestCase {
	use IsolatesStorageTrait;

	public static function invalid_options(): array {
		return array(
			'empty'    => array( '' ),
			'leading'  => array( ' acme_generation' ),
			'trailing' => array( 'acme_generation ' ),
			'overlong' => array( \str_repeat( 'a', 192 ) ),
		);
	}

	#[DataProvider( 'invalid_options' )]
	public function test_an_option_name_the_options_table_would_not_keep_is_rejected( string $option ): void {
		$this->expectException( InvalidArgumentException::class );

		new OptionCacheGeneration( $option );
	}

	public function test_construction_reads_and_writes_nothing(): void {
		global $wpdb;

		$queries = $wpdb->num_queries;
		new OptionCacheGeneration( \str_pad( "{$this->prefix}_generation", 191, 'g' ) );

		self::assertSame( $queries, $wpdb->num_queries );
	}
}
