<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Unit\Helpers;

use DeepWebSolutions\Framework\Utilities\Helpers\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( Request::class )]
final class RequestTest extends TestCase {
	public function test_wp_parse_args_recursive_merges_nested_associative_arrays(): void {
		self::assertSame(
			array(
				'display' => array(
					'mode'  => 'compact',
					'limit' => 10,
				),
				'enabled' => true,
			),
			Request::wp_parse_args_recursive(
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

	public function test_wp_parse_args_recursive_treats_list_arrays_as_leaf_values(): void {
		self::assertSame(
			array( 'ids' => array( 3 ) ),
			Request::wp_parse_args_recursive(
				array( 'ids' => array( 3 ) ),
				array( 'ids' => array( 1, 2 ) ),
			),
		);
	}

	public function test_wp_parse_args_recursive_adds_unknown_argument_keys(): void {
		self::assertSame(
			array(
				'a' => 'default',
				'b' => 'provided',
			),
			Request::wp_parse_args_recursive( array( 'b' => 'provided' ), array( 'a' => 'default' ) ),
		);
	}

	public function test_wp_parse_args_recursive_lets_a_scalar_argument_replace_an_array_default(): void {
		self::assertSame(
			array( 'display' => 'compact' ),
			Request::wp_parse_args_recursive(
				array( 'display' => 'compact' ),
				array( 'display' => array( 'mode' => 'full' ) ),
			),
		);
	}
}
