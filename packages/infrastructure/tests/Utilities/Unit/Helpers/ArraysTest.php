<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Unit\Helpers;

use DeepWebSolutions\Framework\Utilities\Helpers\Arrays;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( Arrays::class )]
final class ArraysTest extends TestCase {
	public function test_parse_args_recursive_merges_nested_associative_arrays(): void {
		self::assertSame(
			array(
				'display' => array(
					'mode'  => 'compact',
					'limit' => 10,
				),
				'enabled' => true,
			),
			Arrays::parse_args_recursive(
				array( 'display' => array( 'mode' => 'compact' ) ),
				array(
					'display' => array(
						'mode'  => 'full',
						'limit' => 10,
					),
					'enabled' => true,
				),
			),
		);
	}

	public function test_parse_args_recursive_treats_list_arrays_as_leaf_values(): void {
		self::assertSame(
			array( 'ids' => array( 3 ) ),
			Arrays::parse_args_recursive(
				array( 'ids' => array( 3 ) ),
				array( 'ids' => array( 1, 2 ) ),
			),
		);
	}

	public function test_parse_args_recursive_adds_unknown_argument_keys(): void {
		self::assertSame(
			array(
				'a' => 'default',
				'b' => 'provided',
			),
			Arrays::parse_args_recursive( array( 'b' => 'provided' ), array( 'a' => 'default' ) ),
		);
	}

	public function test_parse_args_recursive_lets_a_scalar_argument_replace_an_array_default(): void {
		self::assertSame(
			array( 'display' => 'compact' ),
			Arrays::parse_args_recursive(
				array( 'display' => 'compact' ),
				array( 'display' => array( 'mode' => 'full' ) ),
			),
		);
	}

	public function test_insert_after_preserves_associative_keys_and_order(): void {
		self::assertSame(
			array(
				'a' => 'A',
				'b' => 'B',
				'x' => 'X',
				'c' => 'C',
			),
			Arrays::insert_after(
				array(
					'a' => 'A',
					'b' => 'B',
					'c' => 'C',
				),
				'b',
				array( 'x' => 'X' ),
			),
		);
	}

	public function test_insert_after_appends_when_the_key_is_missing(): void {
		self::assertSame(
			array(
				'a' => 'A',
				'b' => 'B',
				'x' => 'X',
			),
			Arrays::insert_after(
				array(
					'a' => 'A',
					'b' => 'B',
				),
				'missing',
				array( 'x' => 'X' ),
			),
		);
	}

	public function test_insert_after_splices_list_arrays(): void {
		self::assertSame(
			array( 'a', 'b', 'x', 'y', 'c' ),
			Arrays::insert_after( array( 'a', 'b', 'c' ), 1, array( 'x', 'y' ) ),
		);
	}

	public function test_insert_after_inserted_key_wins_over_a_colliding_tail_key(): void {
		self::assertSame(
			array(
				'a' => 'A',
				'c' => 'X',
				'b' => 'B',
			),
			Arrays::insert_after(
				array(
					'a' => 'A',
					'b' => 'B',
					'c' => 'C',
				),
				'a',
				array( 'c' => 'X' ),
			),
		);
	}
}
