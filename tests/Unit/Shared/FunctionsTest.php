<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Unit\Shared;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function DeepWebSolutions\Framework\Shared\insert_after;
use function DeepWebSolutions\Framework\Shared\interpolate_log_message;
use function DeepWebSolutions\Framework\Shared\parse_int;

#[CoversFunction( 'DeepWebSolutions\Framework\Shared\insert_after' )]
#[CoversFunction( 'DeepWebSolutions\Framework\Shared\interpolate_log_message' )]
#[CoversFunction( 'DeepWebSolutions\Framework\Shared\parse_int' )]
final class FunctionsTest extends TestCase {
	public static function insertions(): array {
		return array(
			'associative'           => array(
				array(
					'a' => 'A',
					'b' => 'B',
					'c' => 'C',
				),
				'b',
				array( 'x' => 'X' ),
				array(
					'a' => 'A',
					'b' => 'B',
					'x' => 'X',
					'c' => 'C',
				),
			),
			'missing-key'           => array(
				array(
					'a' => 'A',
					'b' => 'B',
				),
				'missing',
				array( 'x' => 'X' ),
				array(
					'a' => 'A',
					'b' => 'B',
					'x' => 'X',
				),
			),
			'list'                  => array( array( 'a', 'b', 'c' ), 1, array( 'x', 'y' ), array( 'a', 'b', 'x', 'y', 'c' ) ),
			'colliding-tail-key'    => array(
				array(
					'a' => 'A',
					'b' => 'B',
					'c' => 'C',
				),
				'a',
				array( 'c' => 'X' ),
				array(
					'a' => 'A',
					'c' => 'X',
					'b' => 'B',
				),
			),
			'empty'                 => array( array(), 'a', array( 'x' => 'X' ), array( 'x' => 'X' ) ),
			'empty-with-list-entry' => array( array(), 0, array( 'x' ), array( 'x' ) ),
		);
	}

	#[DataProvider( 'insertions' )]
	public function test_insert_after_places_the_entries_after_the_key( array $items, int|string $key, array $insert, array $expected ): void {
		self::assertSame( $expected, insert_after( $items, $key, $insert ) );
	}

	public static function log_messages(): array {
		$stringable = new class() implements \Stringable {
			public function __toString(): string {
				return 'stringable';
			}
		};

		return array(
			'string'       => array( 'Hello {name}.', array( 'name' => 'world' ), 'Hello world.' ),
			'integer'      => array( '{count} items', array( 'count' => 3 ), '3 items' ),
			'float'        => array( '{ratio} ratio', array( 'ratio' => 1.5 ), '1.5 ratio' ),
			'boolean'      => array( 'flag {flag}', array( 'flag' => true ), 'flag 1' ),
			'null'         => array( 'value [{value}]', array( 'value' => null ), 'value []' ),
			'stringable'   => array( 'from {object}', array( 'object' => $stringable ), 'from stringable' ),
			'array'        => array( 'data {data}', array( 'data' => array( 1 ) ), 'data {data}' ),
			'plain-object' => array( 'data {data}', array( 'data' => new \stdClass() ), 'data {data}' ),
			'missing-key'  => array( 'Hello {name}.', array(), 'Hello {name}.' ),
		);
	}

	#[DataProvider( 'log_messages' )]
	public function test_interpolate_log_message_replaces_only_printable_values( string $message, array $context, string $expected ): void {
		self::assertSame( $expected, interpolate_log_message( $message, $context ) );
	}

	public static function integers(): array {
		return array(
			'digits'          => array( '12', 12 ),
			'padded-digits'   => array( ' 4 ', 4 ),
			'trailing-letter' => array( '12abc', null ),
			'array'           => array( array(), null ),
			'null'            => array( null, null ),
		);
	}

	#[DataProvider( 'integers' )]
	public function test_parse_int_returns_the_integer_or_null( mixed $value, ?int $expected ): void {
		self::assertSame( $expected, parse_int( $value ) );
	}
}
