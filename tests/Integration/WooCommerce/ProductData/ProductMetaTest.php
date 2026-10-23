<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\WooCommerce\ProductData;

use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesStorageTrait;
use DeepWebSolutions\Framework\WooCommerce\ProductData\ProductMeta;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( ProductMeta::class )]
final class ProductMetaTest extends TestCase {
	use IsolatesHooksTrait;
	use IsolatesStorageTrait;
	use ProductFixturesTrait;

	public static function stored_values(): array {
		return array(
			'text'         => array( 'abc' ),
			'empty-string' => array( '' ),
			'empty-list'   => array( array() ),
			'list'         => array( array( 'a', 'b' ) ),
		);
	}

	public function test_a_product_without_the_meta_reads_as_the_fallback(): void {
		$product = $this->make_product();

		self::assertSame( '', ProductMeta::get_value( $product, "_{$this->prefix}_key" ) );
		self::assertSame( 'yes', ProductMeta::get_value( $product, "_{$this->prefix}_key", 'yes' ) );
	}

	#[DataProvider( 'stored_values' )]
	public function test_a_stored_value_reads_as_stored_never_as_the_fallback( mixed $stored ): void {
		$product = $this->make_product( array( "_{$this->prefix}_key" => $stored ) );

		self::assertSame( $stored, ProductMeta::get_value( \wc_get_product( $product->get_id() ), "_{$this->prefix}_key", 'fallback' ) );
	}

	public function test_the_view_context_applies_woocommerces_meta_filter_and_the_edit_context_does_not(): void {
		$product = $this->make_product( array( "_{$this->prefix}_key" => 'stored' ) );
		\add_filter( "woocommerce_product_get__{$this->prefix}_key", static fn (): string => 'filtered' );

		self::assertSame( 'filtered', ProductMeta::get_value( $product, "_{$this->prefix}_key" ) );
		self::assertSame( 'stored', ProductMeta::get_value( $product, "_{$this->prefix}_key", '', 'edit' ) );
	}
}
