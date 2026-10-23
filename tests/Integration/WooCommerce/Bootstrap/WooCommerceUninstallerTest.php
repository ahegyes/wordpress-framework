<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\WooCommerce\Bootstrap;

use DeepWebSolutions\Framework\Bootstrap\Uninstaller;
use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\RunsAdminRequestsTrait;
use DeepWebSolutions\Framework\WooCommerce\Bootstrap\WooCommerceUninstaller;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( WooCommerceUninstaller::class )]
final class WooCommerceUninstallerTest extends TestCase {
	use CreatesUsersTrait;
	use IsolatesHooksTrait;
	use OrderMetaFixturesTrait;
	use RunsAdminRequestsTrait;

	protected const string FIXTURE = __DIR__ . '/../../../Fixtures/WooCommerce/acme-uninstall.php';

	#[\Override]
	public static function setUpBeforeClass(): void {
		require_once \dirname( __DIR__, 4 ) . '/packages/bootstrap/uninstall.php';
		require_once \dirname( __DIR__, 4 ) . '/packages/woocommerce/uninstall.php';
	}

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

	public static function unreadable_values(): array {
		return array(
			'not-a-list'   => array( 'order_meta', 'x' ),
			'empty-prefix' => array( 'order_meta_prefixes', array( '' ) ),
			'not-a-string' => array( 'order_item_meta', array( 5 ) ),
		);
	}

	public function test_the_handlers_cover_the_woocommerce_footprint_keys(): void {
		self::assertSame( array( 'order_meta', 'order_meta_prefixes', 'order_item_meta' ), \array_keys( WooCommerceUninstaller::get_handlers() ) );
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
		self::assertSame( 'x', \get_post_meta( $this->order_id, "_{$this->prefix}_flag", true ) );

		$this->uninstall( $this->order_footprint() );

		self::assertSame( '', \get_post_meta( $this->order_id, "_{$this->prefix}_flag", true ) );
	}

	#[DataProvider( 'cache_groups' )]
	public function test_a_consenting_uninstall_flushes_the_cache_group_woocommerce_keeps_orders_and_items_in( string $group ): void {
		\wp_cache_set( "{$this->prefix}_entry", 'x', $group );

		$this->uninstall( $this->order_footprint() );

		self::assertFalse( \wp_cache_get( "{$this->prefix}_entry", $group ) );
	}

	public function test_the_handlers_leave_the_network_pass_alone(): void {
		$this->seed_order_meta();

		foreach ( WooCommerceUninstaller::get_handlers() as $key => $handler ) {
			$handler(
				$this->order_footprint()[ $key ],
				array(
					'scope'       => 'network',
					'site_id'     => \get_current_blog_id(),
					'plugin_file' => self::PLUGIN_FILE,
				)
			);
		}

		self::assertSame( $this->sorted( $this->order_meta_keys() ), $this->meta_keys_in( 'postmeta' ) );
		self::assertSame( $this->sorted( $this->order_meta_keys() ), $this->meta_keys_in( 'wc_orders_meta' ) );
	}

	#[DataProvider( 'unreadable_values' )]
	public function test_a_value_a_handler_cannot_read_is_logged_and_skipped( string $key, mixed $value ): void {
		$this->seed_order_meta();

		$lines = $this->capture_error_log( fn () => $this->uninstall( array( $key => $value ) ) );

		self::assertCount( 1, $lines );
		self::assertStringEndsWith( "] [acme-uninstall/acme-uninstall.php] Uninstall skipped the footprint keys it cannot read: $key", $lines[0] );
		self::assertSame( $this->sorted( $this->order_meta_keys() ), $this->meta_keys_in( 'postmeta' ) );
	}

	public function test_with_woocommerce_deactivated_a_consenting_uninstall_still_deletes_from_both_order_tables(): void {
		$this->seed_order_meta();
		\update_option( "{$this->prefix}_remove_data", 'yes' );

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
		\update_option( "{$this->prefix}_remove_data", 'yes' );

		Uninstaller::run(
			self::PLUGIN_FILE,
			$footprint,
			array(
				'consent'  => array( 'option' => "{$this->prefix}_remove_data" ),
				'handlers' => WooCommerceUninstaller::get_handlers(),
			)
		);
	}

	protected function sorted( array $keys ): array {
		\sort( $keys );

		return $keys;
	}
}
