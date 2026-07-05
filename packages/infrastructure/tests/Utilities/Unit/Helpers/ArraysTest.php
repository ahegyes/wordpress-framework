<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Unit\Helpers;

use DeepWebSolutions\Framework\Utilities\Helpers\Arrays;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( Arrays::class )]
final class ArraysTest extends TestCase {
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
