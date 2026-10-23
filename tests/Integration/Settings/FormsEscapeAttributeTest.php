<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Settings;

use DeepWebSolutions\Framework\Settings\Forms;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( Forms::class )]
final class FormsEscapeAttributeTest extends TestCase {
	public static function values(): array {
		return array(
			'plain'             => array( 'Acme', 'Acme' ),
			'ampersand'         => array( 'R&D', 'R&amp;D' ),
			'stored-entity'     => array( 'R&amp;D', 'R&amp;amp;D' ),
			'quotes-and-markup' => array( '"<b>\'', '&quot;&lt;b&gt;&#039;' ),
		);
	}

	#[DataProvider( 'values' )]
	public function test_a_value_reads_back_exactly_as_given_after_the_browser_decodes_it( string $value, string $escaped ): void {
		self::assertSame( $escaped, Forms::escape_attribute( $value ) );
		self::assertSame( $value, \html_entity_decode( $escaped, \ENT_QUOTES | \ENT_HTML5 ) );
	}

	public function test_woocommerces_own_escape_leaves_an_escaped_value_unchanged(): void {
		$escaped = Forms::escape_attribute( 'R&amp;D "quoted"' );

		self::assertSame( $escaped, \esc_attr( $escaped ) );
	}
}
