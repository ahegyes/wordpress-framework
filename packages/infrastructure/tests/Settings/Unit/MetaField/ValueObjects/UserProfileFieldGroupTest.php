<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Tests\Unit\MetaField\ValueObjects;

use DeepWebSolutions\Framework\Settings\MetaField\ValueObjects\FieldGroup;
use DeepWebSolutions\Framework\Settings\MetaField\ValueObjects\UserProfileFieldGroup;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesFunction;
use PHPUnit\Framework\TestCase;

#[CoversClass( UserProfileFieldGroup::class )]
#[UsesClass( FieldGroup::class )]
#[UsesFunction( 'DeepWebSolutions\Framework\Shared\Identifier\is_valid_identifier' )]
final class UserProfileFieldGroupTest extends TestCase {
	public function test_it_wraps_a_field_group_and_shows_on_the_own_profile_by_default(): void {
		$group   = $this->group();
		$profile = new UserProfileFieldGroup( group: $group );

		self::assertSame( $group, $profile->group );
		self::assertTrue( $profile->on_own_profile );
	}

	public function test_it_can_be_restricted_to_admins_editing_another_user(): void {
		$profile = new UserProfileFieldGroup( group: $this->group(), on_own_profile: false );

		self::assertFalse( $profile->on_own_profile );
	}

	private function group(): FieldGroup {
		return new FieldGroup(
			id: 'prefs',
			title: 'Preferences',
			fields_provider: static fn ( int $object_id ): array => array(),
		);
	}
}
