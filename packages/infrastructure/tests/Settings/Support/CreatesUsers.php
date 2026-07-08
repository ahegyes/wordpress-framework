<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Tests\Support;

trait CreatesUsers {
	protected function make_user( string $login, string $role = 'subscriber' ): int {
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

	protected function make_admin( string $login ): int {
		return $this->make_user( $login, 'administrator' );
	}
}
