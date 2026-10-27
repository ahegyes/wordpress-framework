<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Php74;

use DeepWebSolutions\Framework\Tests\Php74\Support\RunsPhp74SiteTrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class BootstrapPersonaTest extends TestCase {
	use RunsPhp74SiteTrait;

	public function test_the_plugin_passes_its_floors_on_php_7_4_below_wordpress_6_4_and_hooks_its_updater(): void {
		$record = $this->php74_request( array( $this->install_on_php74( 'personas/bootstrap', 'acme-persona-bootstrap' ) ) );

		self::assertStringStartsWith( '7.4.', $record['php'] );
		self::assertStringStartsWith( '6.3.', $record['wordpress'] );
		self::assertFalse( $record['wp_admin_notice'] );
		self::assertTrue( $record['updater_hooked'] );
		self::assertSame( '<div class="notice notice-info"><p>Acme Persona Bootstrap is running.</p></div>', $record['notices'] );
	}

	public function test_a_copy_requiring_a_newer_php_shows_wordpress_core_s_message_through_the_fallback_and_keeps_its_updater(): void {
		$record = $this->php74_request( array( $this->install_on_php74( 'personas/bootstrap', 'acme-persona-bootstrap', array( 'Requires PHP' => '8.0' ) ) ) );

		self::assertTrue( $record['updater_hooked'] );
		self::assertSame(
			'<div class="notice notice-error"><p><strong>Error:</strong> Current PHP version (' . $record['php'] . ') does not meet minimum requirements for Acme Persona Bootstrap. The plugin requires PHP 8.0.</p><p><a href="https://wordpress.org/support/update-php/">Learn more about updating PHP</a>.</p></div>',
			$record['notices']
		);
	}
}
