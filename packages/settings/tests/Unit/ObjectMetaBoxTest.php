<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Tests\Unit;

use DeepWebSolutions\Framework\Settings\ObjectField\Exceptions\InvalidObjectMetaBoxException;
use DeepWebSolutions\Framework\Settings\ObjectField\ValueObjects\ObjectMetaBox;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( ObjectMetaBox::class )]
final class ObjectMetaBoxTest extends TestCase {
	public function test_minimal_construction_round_trips(): void {
		$provider = static fn ( int $object_id ): array => array();
		$box      = new ObjectMetaBox(
			id: 'lpm_unlock',
			title: 'Unlock',
			screen: 'shop_order',
			context: 'side',
			priority: 'high',
			fields_provider: $provider,
		);

		self::assertSame( 'lpm_unlock', $box->id );
		self::assertSame( 'Unlock', $box->title );
		self::assertSame( 'shop_order', $box->screen );
		self::assertSame( 'side', $box->context );
		self::assertSame( 'high', $box->priority );
		self::assertInstanceOf( \Closure::class, $box->fields_provider );
		self::assertNull( $box->render );
		self::assertNull( $box->save );
	}

	public function test_an_id_outside_the_charset_throws(): void {
		$this->expectException( InvalidObjectMetaBoxException::class );

		// A meta-box id reaches WordPress meta-box markup unescaped, so it must stay within the charset.
		new ObjectMetaBox(
			id: 'Bad Id!',
			title: 'M',
			screen: 'shop_order',
			context: 'side',
			priority: 'default',
			fields_provider: static fn ( int $object_id ): array => array(),
		);
	}

	public function test_array_callable_fields_provider_is_normalized_to_a_closure(): void {
		$source = new class() {
			/**
			 * @return list<\DeepWebSolutions\Framework\Settings\ValueObjects\SettingsField>
			 */
			public function fields( int $object_id ): array {
				return array();
			}
		};
		$box = new ObjectMetaBox(
			id: 'mb',
			title: 'M',
			screen: 's',
			context: 'normal',
			priority: 'default',
			fields_provider: array( $source, 'fields' ),
		);

		self::assertInstanceOf( \Closure::class, $box->fields_provider );
	}

	public function test_render_and_save_callables_are_normalized_to_closures(): void {
		$box = new ObjectMetaBox(
			id: 'mb',
			title: 'M',
			screen: 's',
			context: 'normal',
			priority: 'default',
			fields_provider: static fn ( int $object_id ): array => array(),
			render: static fn (): string => '',
			save: 'strlen',
		);

		self::assertInstanceOf( \Closure::class, $box->render );
		self::assertInstanceOf( \Closure::class, $box->save );
	}
}
