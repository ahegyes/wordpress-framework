<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Tests\Unit\MetaField;

use DeepWebSolutions\Framework\Settings\MetaField\ValueObjects\MetaBoxPlacement;
use DeepWebSolutions\Framework\Shared\ValueObject\AbstractValueObject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesFunction;
use PHPUnit\Framework\TestCase;

#[CoversClass( MetaBoxPlacement::class )]
#[UsesClass( AbstractValueObject::class )]
#[UsesFunction( 'DeepWebSolutions\Framework\Shared\Reflection\get_public_property_names' )]
#[UsesFunction( 'DeepWebSolutions\Framework\Shared\Reflection\convert_to_primitives' )]
final class MetaBoxPlacementTest extends TestCase {
	public function test_construction_round_trips_with_no_capability_override(): void {
		$placement = new MetaBoxPlacement(
			screen: 'shop_order',
			context: 'side',
			priority: 'high',
		);

		self::assertNull( $placement->capability );
	}

	public function test_a_capability_override_is_carried(): void {
		$placement = new MetaBoxPlacement(
			screen: 'product',
			context: 'normal',
			priority: 'default',
			capability: 'manage_woocommerce',
		);

		self::assertSame( 'manage_woocommerce', $placement->capability );
	}

	public function test_equals_is_structural_over_all_four_properties(): void {
		$placement = new MetaBoxPlacement( screen: 'post', context: 'side', priority: 'high', capability: 'edit_pages' );

		self::assertTrue( $placement->equals( new MetaBoxPlacement( screen: 'post', context: 'side', priority: 'high', capability: 'edit_pages' ) ) );
		self::assertFalse( $placement->equals( new MetaBoxPlacement( screen: 'page', context: 'side', priority: 'high', capability: 'edit_pages' ) ) );
		self::assertFalse( $placement->equals( new MetaBoxPlacement( screen: 'post', context: 'normal', priority: 'high', capability: 'edit_pages' ) ) );
		self::assertFalse( $placement->equals( new MetaBoxPlacement( screen: 'post', context: 'side', priority: 'low', capability: 'edit_pages' ) ) );
		self::assertFalse( $placement->equals( new MetaBoxPlacement( screen: 'post', context: 'side', priority: 'high' ) ) );
	}

	public function test_json_serialization_carries_all_four_properties(): void {
		$placement = new MetaBoxPlacement( screen: 'post', context: 'side', priority: 'default' );

		self::assertSame(
			array(
				'screen'     => 'post',
				'context'    => 'side',
				'priority'   => 'default',
				'capability' => null,
			),
			$placement->jsonSerialize(),
		);
	}
}
