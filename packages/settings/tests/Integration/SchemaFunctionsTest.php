<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Tests\Integration;

use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsField;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function DeepWebSolutions\Framework\Settings\Schema\is_field_editable_by_current_user;

#[CoversFunction( 'DeepWebSolutions\Framework\Settings\Schema\is_field_editable_by_current_user' )]
#[UsesClass( SettingsField::class )]
final class SchemaFunctionsTest extends TestCase {
	private int $original_user;
	private int $admin;
	private int $subscriber;

	protected function setUp(): void {
		parent::setUp();
		$this->original_user = \get_current_user_id();
		$this->admin         = $this->make_user( 'dws-field-editable-admin', 'administrator' );
		$this->subscriber    = $this->make_user( 'dws-field-editable-subscriber', 'subscriber' );
	}

	protected function tearDown(): void {
		\wp_set_current_user( $this->original_user );
		parent::tearDown();
	}

	public function test_a_field_with_no_capability_is_always_editable(): void {
		\wp_set_current_user( $this->subscriber );
		$field = new SettingsField( id: 'open', type: 'text', label: 'Open' );

		self::assertTrue( is_field_editable_by_current_user( $field ) );
	}

	public function test_a_field_is_editable_when_the_current_user_holds_its_capability(): void {
		\wp_set_current_user( $this->admin );
		$field = new SettingsField( id: 'secret', type: 'text', label: 'Secret', capability: 'manage_options' );

		self::assertTrue( is_field_editable_by_current_user( $field ) );
	}

	public function test_a_field_is_not_editable_when_the_current_user_lacks_its_capability(): void {
		\wp_set_current_user( $this->subscriber );
		$field = new SettingsField( id: 'secret', type: 'text', label: 'Secret', capability: 'manage_options' );

		self::assertFalse( is_field_editable_by_current_user( $field ) );
	}

	private function make_user( string $login, string $role ): int {
		$existing = \get_user_by( 'login', $login );
		if ( $existing instanceof \WP_User ) {
			return $existing->ID;
		}

		$id = \wp_insert_user(
			array(
				'user_login' => $login,
				'user_pass'  => 'password',
				'role'       => $role,
			),
		);
		self::assertIsInt( $id );

		return $id;
	}
}
