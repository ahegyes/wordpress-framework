<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Tests\Unit\MetaField;

use DeepWebSolutions\Framework\Settings\MetaField\Exceptions\InvalidMetaBoxPlacementException;
use DeepWebSolutions\Framework\Settings\MetaField\ValueObjects\MetaBoxPlacement;
use DeepWebSolutions\Framework\Shared\ValueObject\AbstractValueObject;
use DeepWebSolutions\Framework\Shared\ValueObject\Exceptions\InvalidValueObjectException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesFunction;
use PHPUnit\Framework\TestCase;

#[CoversClass( MetaBoxPlacement::class )]
#[UsesClass( AbstractValueObject::class )]
#[UsesClass( InvalidMetaBoxPlacementException::class )]
#[UsesClass( InvalidValueObjectException::class )]
#[UsesFunction( 'DeepWebSolutions\Framework\Shared\Identifier\is_valid_identifier' )]
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

	#[DataProvider( 'invalid_screens' )]
	public function test_a_screen_outside_the_identifier_charset_is_rejected( string $invalid_screen ): void {
		$this->expectException( InvalidMetaBoxPlacementException::class );

		new MetaBoxPlacement( screen: $invalid_screen, context: 'normal', priority: 'default' );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function invalid_screens(): array {
		return array(
			'empty'              => array( '' ),
			'leading digit'      => array( '1post' ),
			'leading underscore' => array( '_post' ),
			'uppercase'          => array( 'Post' ),
			'space'              => array( 'shop order' ),
		);
	}

	public function test_a_context_outside_the_closed_set_is_rejected(): void {
		$this->expectException( InvalidMetaBoxPlacementException::class );

		new MetaBoxPlacement( screen: 'post', context: 'sidebar', priority: 'default' );
	}

	public function test_a_priority_outside_the_closed_set_is_rejected(): void {
		$this->expectException( InvalidMetaBoxPlacementException::class );

		new MetaBoxPlacement( screen: 'post', context: 'side', priority: 'urgent' );
	}

	#[DataProvider( 'valid_contexts_and_priorities' )]
	public function test_every_context_and_priority_in_the_closed_sets_is_accepted( string $context, string $priority ): void {
		$placement = new MetaBoxPlacement( screen: 'post', context: $context, priority: $priority );

		self::assertSame( $context, $placement->context );
		self::assertSame( $priority, $placement->priority );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function valid_contexts_and_priorities(): array {
		return array(
			'normal + high'      => array( 'normal', 'high' ),
			'side + core'        => array( 'side', 'core' ),
			'advanced + default' => array( 'advanced', 'default' ),
			'normal + low'       => array( 'normal', 'low' ),
		);
	}

	public function test_get_capability_returns_the_override_or_the_edit_post_fallback(): void {
		self::assertSame( 'edit_post', ( new MetaBoxPlacement( screen: 'post', context: 'side', priority: 'default' ) )->get_capability() );
		self::assertSame( 'manage_woocommerce', ( new MetaBoxPlacement( screen: 'product', context: 'side', priority: 'default', capability: 'manage_woocommerce' ) )->get_capability() );
	}
}
