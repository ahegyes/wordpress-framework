<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\WooCommerce;

use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesStorageTrait;
use DeepWebSolutions\Framework\WooCommerce\Orders\OrderMetaStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( OrderMetaStore::class )]
final class OrderMetaStoreTest extends TestCase {
	use IsolatesHooksTrait;
	use IsolatesStorageTrait;
	use OrderFixturesTrait;

	#[DataProvider( 'order_storages' )]
	public function test_only_the_keys_the_order_has_are_returned_with_their_stored_values( string $storage ): void {
		$this->use_order_storage( $storage );
		$order = $this->make_order(
			array(
				"_{$this->prefix}_text"  => 'stored',
				"_{$this->prefix}_empty" => '',
				"_{$this->prefix}_list"  => array(),
			)
		);

		$values = ( new OrderMetaStore() )->get_values( $order->get_id(), array( "_{$this->prefix}_text", "_{$this->prefix}_empty", "_{$this->prefix}_list", "_{$this->prefix}_absent" ) );

		self::assertSame(
			array(
				"_{$this->prefix}_text"  => 'stored',
				"_{$this->prefix}_empty" => '',
				"_{$this->prefix}_list"  => array(),
			),
			$values
		);
	}

	#[DataProvider( 'order_storages' )]
	public function test_values_are_stored_as_given_text_backslashes_included( string $storage ): void {
		$this->use_order_storage( $storage );
		$order = $this->make_order( array( "_{$this->prefix}_text" => 'old' ) );

		( new OrderMetaStore() )->update_values(
			$order->get_id(),
			array(
				"_{$this->prefix}_text" => 'O\'Brien C:\\dir',
				"_{$this->prefix}_list" => array( 'red', 'teal' ),
			)
		);

		self::assertSame( array( 'O\'Brien C:\\dir' ), $this->order_meta( $order->get_id(), "_{$this->prefix}_text" ) );
		self::assertSame( array( array( 'red', 'teal' ) ), $this->order_meta( $order->get_id(), "_{$this->prefix}_list" ) );
	}

	#[DataProvider( 'order_storages' )]
	public function test_a_stored_empty_value_survives_a_round_trip( string $storage ): void {
		$this->use_order_storage( $storage );
		$order = $this->make_order();
		$store = new OrderMetaStore();

		$store->update_values(
			$order->get_id(),
			array(
				"_{$this->prefix}_empty" => '',
				"_{$this->prefix}_list"  => array(),
			)
		);
		\wp_cache_flush();

		self::assertSame(
			array(
				"_{$this->prefix}_empty" => '',
				"_{$this->prefix}_list"  => array(),
			),
			$store->get_values( $order->get_id(), array( "_{$this->prefix}_empty", "_{$this->prefix}_list" ) )
		);
	}

	#[DataProvider( 'order_storages' )]
	public function test_a_write_saves_the_order_once_so_its_update_hook_sees_the_values( string $storage ): void {
		$this->use_order_storage( $storage );
		$order = $this->make_order();
		$seen  = array();
		\add_action(
			'woocommerce_update_order',
			function ( int $order_id ) use ( &$seen ): void {
				$seen[] = \wc_get_order( $order_id )->get_meta( "_{$this->prefix}_b", true, 'edit' );
			}
		);

		( new OrderMetaStore() )->update_values(
			$order->get_id(),
			array(
				"_{$this->prefix}_a" => 'one',
				"_{$this->prefix}_b" => 'two',
			)
		);

		self::assertSame( array( 'two' ), $seen );
	}
}
