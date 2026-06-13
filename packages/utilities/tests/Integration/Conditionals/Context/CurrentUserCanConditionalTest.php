<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Integration\Conditionals\Context;

use DeepWebSolutions\Framework\Utilities\Conditionals\Context\CurrentUserCanConditional;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( CurrentUserCanConditional::class )]
final class CurrentUserCanConditionalTest extends TestCase {
	private int $original_user;

	protected function setUp(): void {
		parent::setUp();
		$this->original_user = \get_current_user_id();
		\wp_set_current_user( 0 );
	}

	protected function tearDown(): void {
		\wp_set_current_user( $this->original_user );
		parent::tearDown();
	}

	public function test_anonymous_user_lacks_manage_options(): void {
		$conditional = new CurrentUserCanConditional( 'manage_options' );

		self::assertFalse( $conditional->is_met() );
	}
}
