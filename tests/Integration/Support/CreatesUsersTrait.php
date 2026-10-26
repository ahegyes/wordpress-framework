<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Support;

trait CreatesUsersTrait {
	protected function make_user( string $login, string $role ): int {
		$existing = get_user_by( 'login', $login );
		if ( $existing instanceof \WP_User ) {
			$existing->set_role( $role );
			return $existing->ID;
		}

		$user_id = wp_insert_user(
			array(
				'user_login' => $login,
				'user_pass'  => wp_generate_password(),
				'role'       => $role,
			)
		);
		self::assertIsInt( $user_id );

		return $user_id;
	}
}
