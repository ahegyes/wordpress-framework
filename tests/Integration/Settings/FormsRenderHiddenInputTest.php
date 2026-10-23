<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Settings;

use DeepWebSolutions\Framework\Settings\Forms;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( Forms::class )]
final class FormsRenderHiddenInputTest extends TestCase {
	public function test_the_input_posts_its_value_under_its_name_with_both_escaped(): void {
		self::assertSame( '<input type="hidden" name="acme[&quot;x&quot;]" value="R&amp;amp;D" />', Forms::render_hidden_input( 'acme["x"]', 'R&amp;D' ) );
	}
}
