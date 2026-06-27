<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Tests\Unit\MetaField;

use DeepWebSolutions\Framework\Settings\MetaField\ValueObjects\MetaBoxPlacement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( MetaBoxPlacement::class )]
final class MetaBoxPlacementTest extends TestCase {
	public function test_construction_round_trips_with_no_capability_override(): void {
		$placement = new MetaBoxPlacement(
			screen: 'shop_order',
			context: 'side',
			priority: 'high',
		);

		self::assertSame( 'shop_order', $placement->screen );
		self::assertSame( 'side', $placement->context );
		self::assertSame( 'high', $placement->priority );
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
}
