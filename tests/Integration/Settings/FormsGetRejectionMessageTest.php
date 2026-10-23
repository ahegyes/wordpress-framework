<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Settings;

use DeepWebSolutions\Framework\Settings\Forms;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( Forms::class )]
final class FormsGetRejectionMessageTest extends TestCase {
	public function test_a_worded_rejection_names_the_field_and_what_to_enter_as_plain_text(): void {
		self::assertSame( "Customer's <b>note</b> was not saved, so it keeps its previous value. Use at most four characters.", Forms::get_rejection_message( "Customer's <b>note</b>", new \WP_Error( 'acme_rejected', 'Use at most four characters.' ) ) );
	}

	public function test_a_silent_rejection_names_the_field_alone(): void {
		self::assertSame( 'Note was not saved because its value is not valid, so it keeps its previous value.', Forms::get_rejection_message( 'Note', new \WP_Error( 'acme_rejected' ) ) );
	}
}
