<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Php74;

use DeepWebSolutions\Framework\Tests\Php74\Support\RunsPhp74SiteTrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ConsumerASafeguardTest extends TestCase {
	use RunsPhp74SiteTrait;

	public function test_a_plugin_below_its_floors_on_php_7_4_keeps_its_safeguard_and_its_updater_and_shows_core_s_message(): void {
		$record = $this->php74_request( array( $this->install_on_php74( 'consumer-a', 'acme-consumer-a' ) ) );

		self::assertSame( array( 'do_not_allow' ), $record['export_caps'] );
		self::assertTrue( $record['updater_hooked'] );
		self::assertStringContainsString( 'do not meet minimum requirements for Acme Consumer A.', $record['notices'] );
	}
}
