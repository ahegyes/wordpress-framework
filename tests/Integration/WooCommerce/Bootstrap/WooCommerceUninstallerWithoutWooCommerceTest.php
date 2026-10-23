<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\WooCommerce\Bootstrap;

use DeepWebSolutions\Framework\Bootstrap\Uninstaller;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use DeepWebSolutions\Framework\WooCommerce\Bootstrap\WooCommerceUninstaller;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass( WooCommerceUninstaller::class )]
#[Group( 'without-woocommerce' )]
final class WooCommerceUninstallerWithoutWooCommerceTest extends TestCase {
	use IsolatesHooksTrait;
	use OrderMetaFixturesTrait;

	#[\Override]
	public static function setUpBeforeClass(): void {
		require_once \dirname( __DIR__, 4 ) . '/packages/bootstrap/uninstall.php';
		require_once \dirname( __DIR__, 4 ) . '/packages/woocommerce/uninstall.php';
	}

	public function test_on_a_site_without_woocommerces_tables_a_consenting_uninstall_deletes_the_order_keys_from_post_meta(): void {
		self::assertFalse( $this->table_exists( 'wc_orders_meta' ) );
		self::assertFalse( $this->table_exists( 'woocommerce_order_itemmeta' ) );
		$this->seed_order_meta();
		\update_option( "{$this->prefix}_remove_data", 'yes' );

		Uninstaller::run(
			self::PLUGIN_FILE,
			$this->order_footprint(),
			array(
				'consent'  => array( 'option' => "{$this->prefix}_remove_data" ),
				'handlers' => WooCommerceUninstaller::get_handlers(),
			)
		);

		self::assertSame( $this->kept_order_meta_keys(), $this->meta_keys_in( 'postmeta' ) );
		self::assertFalse( \get_option( "{$this->prefix}_remove_data" ) );
	}
}
