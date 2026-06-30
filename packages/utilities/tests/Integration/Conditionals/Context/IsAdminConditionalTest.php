<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Integration\Conditionals\Context;

use DeepWebSolutions\Framework\Utilities\Conditionals\Context\IsAdminConditional;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( IsAdminConditional::class )]
final class IsAdminConditionalTest extends TestCase {
	private ?\WP_Screen $original_screen;

	protected function setUp(): void {
		parent::setUp();

		require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		require_once ABSPATH . 'wp-admin/includes/screen.php';

		$this->original_screen = \get_current_screen();
	}

	protected function tearDown(): void {
		if ( null !== $this->original_screen ) {
			\set_current_screen( $this->original_screen );
		} else {
			\set_current_screen( 'front' );
		}

		parent::tearDown();
	}

	public function test_returns_false_outside_admin(): void {
		self::assertFalse( ( new IsAdminConditional() )->is_met() );
	}

	public function test_returns_true_in_admin_context(): void {
		\set_current_screen( 'edit-post' );

		self::assertTrue( ( new IsAdminConditional() )->is_met() );
	}
}
