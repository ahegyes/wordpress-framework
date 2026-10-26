<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\WooCommerce;

use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\WooCommerce\WooCommerceRequirement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass( WooCommerceRequirement::class )]
#[Group( 'without-woocommerce' )]
final class WooCommerceRequirementWithoutWooCommerceTest extends TestCase {
	use CreatesUsersTrait;
	use RequirementFixturesTrait;

	public function test_a_missing_woocommerce_fails_and_only_users_who_can_activate_plugins_see_wordpress_cores_dependency_message(): void {
		self::assertFalse( \defined( 'WC_VERSION' ) );
		$plugin_file = $this->write_plugin(
			array(
				'WC requires at least' => '11.2',
				'Requires Plugins'     => 'woocommerce',
			)
		);
		$requirement = new WooCommerceRequirement( $plugin_file );

		self::assertFalse( $requirement->check() );

		// On a network, only a super admin can activate plugins unless the network enables the plugins menu.
		$super_admin = $this->make_user( 'acme_requirement_super_admin', 'administrator' );
		grant_super_admin( $super_admin );
		$html  = $this->render_notices_as( $super_admin );
		$error = validate_plugin_requirements( plugin_basename( $plugin_file ) );
		revoke_super_admin( $super_admin );

		self::assertInstanceOf( \WP_Error::class, $error );
		self::assertSame( 'plugin_missing_dependencies', $error->get_error_code() );
		$expected = wp_get_admin_notice(
			$error->get_error_message(),
			array(
				'type'           => 'error',
				'paragraph_wrap' => false,
			)
		);
		self::assertSame( wp_kses_post( $expected ), $html );
		self::assertSame( '', $this->render_notices_as( $this->make_user( 'acme_requirement_admin', 'administrator' ) ) );
	}
}
