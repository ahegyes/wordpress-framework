<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Core\Caching;

use DeepWebSolutions\Framework\Caching\OptionCacheGeneration;
use DeepWebSolutions\Framework\Shared\Exception\InvalidArgumentException;
use DeepWebSolutions\Framework\Tests\Integration\Support\InterceptsQueriesTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesStorageTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( OptionCacheGeneration::class )]
final class OptionCacheGenerationTest extends TestCase {
	use InterceptsQueriesTrait;
	use IsolatesHooksTrait;
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

	public static function unusable_values(): array {
		return array(
			'absent' => array( null ),
			'empty'  => array( '' ),
			'array'  => array( array( 'token' ) ),
		);
	}

	#[DataProvider( 'unusable_values' )]
	public function test_no_generation_has_started_until_the_option_holds_a_non_empty_string( mixed $stored ): void {
		if ( ! \is_null( $stored ) ) {
			\update_option( "{$this->prefix}_generation", $stored );
		}

		self::assertNull( $this->generation()->get() );
	}

	public function test_advancing_stores_a_new_autoloaded_token_each_time(): void {
		global $wpdb;

		$generation = $this->generation();

		$first  = $generation->advance();
		$second = $generation->advance();

		self::assertMatchesRegularExpression( '/^[0-9a-f]{16}$/', (string) $first );
		self::assertMatchesRegularExpression( '/^[0-9a-f]{16}$/', (string) $second );
		self::assertNotSame( $first, $second );
		self::assertSame( $second, $generation->get() );
		self::assertSame( 'on', $wpdb->get_var( $wpdb->prepare( 'SELECT autoload FROM %i WHERE option_name = %s', $wpdb->options, "{$this->prefix}_generation" ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Inspects the stored row.
	}

	public function test_a_token_that_cannot_be_stored_is_not_returned(): void {
		global $wpdb;

		$this->fail_queries( "INTO `$wpdb->options`" );

		self::assertNull( $this->generation()->advance() );
	}

	protected function generation(): OptionCacheGeneration {
		return new OptionCacheGeneration( "{$this->prefix}_generation" );
	}
}
