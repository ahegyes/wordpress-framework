<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\WooCommerce\Uninstall;

use DeepWebSolutions\Framework\Core\Uninstall\Uninstaller;
use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\RunsAdminRequestsTrait;
use DeepWebSolutions\Framework\WooCommerce\Uninstall\MetaTypes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( MetaTypes::class )]
final class MetaTypesTest extends TestCase {
	use CreatesUsersTrait;
	use IsolatesHooksTrait;
	use OrderMetaFixturesTrait;
	use RunsAdminRequestsTrait;

	protected const string FIXTURE = __DIR__ . '/../../../Fixtures/WooCommerce/acme-uninstall.php';

	public static function cache_groups(): array {
		return array(
			'orders'          => array( 'orders' ),
			'order-objects'   => array( 'order_objects' ),
			'orders-data'     => array( 'orders_data' ),
			'orders-meta'     => array( 'orders_meta' ),
			'order-items'     => array( 'order-items' ),
			'order-item-meta' => array( 'order_item_meta' ),
		);
	}

	public function test_the_meta_types_are_named_order_and_order_item(): void {
		self::assertSame( array( 'order', 'order_item' ), \array_keys( MetaTypes::DEFINITIONS ) );
	}

	public function test_a_consenting_uninstall_deletes_exactly_the_order_keys_from_post_meta_and_both_order_tables(): void {
		self::assertTrue( $this->table_exists( 'wc_orders_meta' ) );
		$this->seed_order_meta();

		$this->uninstall( $this->order_footprint() );

		self::assertSame( $this->kept_order_meta_keys(), $this->meta_keys_in( 'postmeta' ) );
		self::assertSame( $this->kept_order_meta_keys(), $this->meta_keys_in( 'wc_orders_meta' ) );
		self::assertSame( $this->kept_order_item_meta_keys(), $this->meta_keys_in( 'woocommerce_order_itemmeta' ) );
	}

	public function test_a_deleted_post_meta_key_is_gone_from_the_posts_meta_cache(): void {
		$this->seed_order_meta();
		self::assertSame( 'x', get_post_meta( $this->order_id, "_{$this->prefix}_flag", true ) );

		$this->uninstall( $this->order_footprint() );

		self::assertSame( '', get_post_meta( $this->order_id, "_{$this->prefix}_flag", true ) );
	}

	#[DataProvider( 'cache_groups' )]
	public function test_a_consenting_uninstall_flushes_the_cache_group_woocommerce_keeps_orders_and_items_in( string $group ): void {
		wp_cache_set( "{$this->prefix}_entry", 'x', $group );

		$this->uninstall( $this->order_footprint() );

		self::assertFalse( wp_cache_get( "{$this->prefix}_entry", $group ) );
	}

	public function test_with_woocommerce_deactivated_a_consenting_uninstall_still_deletes_from_both_order_tables(): void {
		$this->seed_order_meta();
		update_option( "{$this->prefix}_remove_data", 'yes' );

		$request = $this->admin_request(
			'GET',
			'admin-post.php',
			$this->make_user( 'acme_uninstall_admin', 'administrator' ),
			self::FIXTURE,
			array(
				'plugin_file' => self::PLUGIN_FILE,
				'footprint'   => $this->order_footprint(),
				'consent'     => array( 'option' => "{$this->prefix}_remove_data" ),
			)
		);

		self::assertSame( array( 'woocommerce loaded' => false ), $request['records'] );
		self::assertSame( $this->kept_order_meta_keys(), $this->meta_keys_in( 'postmeta' ) );
		self::assertSame( $this->kept_order_meta_keys(), $this->meta_keys_in( 'wc_orders_meta' ) );
		self::assertSame( $this->kept_order_item_meta_keys(), $this->meta_keys_in( 'woocommerce_order_itemmeta' ) );
	}

	protected function uninstall( array $footprint ): void {
		update_option( "{$this->prefix}_remove_data", 'yes' );

		Uninstaller::run(
			self::PLUGIN_FILE,
			$footprint,
			array(
				'consent'    => array( 'option' => "{$this->prefix}_remove_data" ),
				'meta_types' => MetaTypes::DEFINITIONS,
			)
		);
	}
}
