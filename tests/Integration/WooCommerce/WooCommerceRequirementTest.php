<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\WooCommerce;

use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\WooCommerce\WooCommerceRequirement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( WooCommerceRequirement::class )]
final class WooCommerceRequirementTest extends TestCase {
	use CreatesUsersTrait;
	use RequirementFixturesTrait;

	// Data providers run in every environment, WooCommerce's or not, so the installed version is resolved in the test.
	public static function met_floors(): array {
		return array(
			'at-the-floor'        => array( 'installed' ),
			'below-the-installed' => array( '11.2' ),
			'no-header'           => array( null ),
		);
	}

	#[DataProvider( 'met_floors' )]
	public function test_woocommerce_meeting_the_floor_passes_and_nobody_sees_a_notice( ?string $floor ): void {
		$headers     = \is_null( $floor ) ? array() : array( 'WC requires at least' => 'installed' === $floor ? \WC_VERSION : $floor );
		$requirement = new WooCommerceRequirement( $this->write_plugin( $headers + array( 'Requires Plugins' => 'woocommerce' ) ) );

		self::assertTrue( $requirement->check() );
		self::assertSame( '', $this->render_notices_as( $this->make_user( 'acme_requirement_admin', 'administrator' ) ) );
	}

	public function test_woocommerce_below_the_floor_fails_and_only_users_who_can_activate_plugins_are_told_to_update_it(): void {
		$requirement = new WooCommerceRequirement(
			$this->write_plugin(
				array(
					'WC requires at least' => '99.1',
					'Requires Plugins'     => 'woocommerce',
				)
			)
		);

		self::assertFalse( $requirement->check() );

		$notice = \Dom\HTMLDocument::createFromString( $this->render_notices_as( $this->make_user( 'acme_requirement_admin', 'administrator' ) ), \LIBXML_NOERROR )->querySelector( '.notice.notice-error' );
		self::assertNotNull( $notice );
		self::assertSame( 'Acme Shop needs WooCommerce 99.1 or later, so its features stay off until WooCommerce is updated.', \trim( $notice->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		self::assertSame( '', $this->render_notices_as( $this->make_user( 'acme_requirement_editor', 'editor' ) ) );
	}

	public function test_the_notice_prints_the_plugins_name_and_floor_as_text(): void {
		$requirement = new WooCommerceRequirement(
			$this->write_plugin(
				array(
					'Plugin Name'          => 'Acme <b>Shop</b>',
					'WC requires at least' => '99.1<i>',
				)
			)
		);
		$requirement->check();

		$html = $this->render_notices_as( $this->make_user( 'acme_requirement_admin', 'administrator' ) );

		self::assertStringContainsString( 'Acme &lt;b&gt;Shop&lt;/b&gt; needs WooCommerce 99.1&lt;i&gt; or later', $html );
	}

	public function test_a_failed_check_reads_translations_only_when_the_notice_renders(): void {
		$requirement  = new WooCommerceRequirement( $this->write_plugin( array( 'WC requires at least' => '99.1' ) ) );
		$translations = 0;
		\add_filter(
			'gettext_wp-framework',
			static function ( string $translation ) use ( &$translations ): string {
				++$translations;

				return $translation;
			}
		);

		$requirement->check();
		self::assertSame( 0, $translations );

		$this->render_notices_as( $this->make_user( 'acme_requirement_admin', 'administrator' ) );
		self::assertGreaterThan( 0, $translations );
	}
}
