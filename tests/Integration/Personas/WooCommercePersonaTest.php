<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Personas;

use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\InstallsConsumersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\SerializesFormsTrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class WooCommercePersonaTest extends TestCase {
	use CreatesUsersTrait;
	use InstallsConsumersTrait;
	use SerializesFormsTrait;

	protected int $admin;
	protected array $spec;

	#[\Override]
	protected function setUp(): void {
		$this->admin = $this->make_user( 'acme_pw_admin', 'administrator' );
		$this->spec  = array( 'activate' => $this->install_consumer( 'personas/woocommerce', 'acme-persona-woocommerce' ) );
	}

	public function test_the_settings_tab_saves_its_field_through_woocommerce_and_logs_the_save_under_the_plugin_s_source(): void {
		$path = 'admin.php?page=wc-settings&tab=acme_pw';
		$data = self::serialize_form( $this->consumer_request( 'GET', $path, $this->admin, $this->spec )['body'], 'form#mainform', array( 'acme_pw_default_box' => 'Large' ) ) + array( 'save' => 'Save changes' );

		$saved = $this->consumer_request( 'POST', $path, $this->admin, $this->spec, $data );

		self::assertSame( 'Large', get_option( 'acme_pw_default_box' ) );
		self::assertContains( array( 'info', 'The packing settings were saved.', 'acme-persona-woocommerce' ), $saved['records']['logs'] ?? array() );
	}

	public function test_the_product_data_tab_saves_its_field_on_the_product_through_the_edit_form(): void {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Acme box' );
		$product->save();

		try {
			$data = self::serialize_form( $this->consumer_request( 'GET', "post.php?post={$product->get_id()}&action=edit", $this->admin, $this->spec )['body'], 'form#post', array( '_acme_pw_box' => 'Small' ) ) + array( 'save' => 'Update' );
			$this->consumer_request( 'POST', 'post.php', $this->admin, $this->spec, $data );

			self::assertSame( 'Small', wc_get_product( $product->get_id() )?->get_meta( '_acme_pw_box' ) );
		} finally {
			$product->delete( true );
		}
	}
}
