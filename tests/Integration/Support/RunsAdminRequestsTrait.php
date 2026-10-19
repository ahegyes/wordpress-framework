<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Support;

use PHPUnit\Framework\Attributes\After;

trait RunsAdminRequestsTrait {
	protected array $session_cookies = array();
	protected array $session_tokens  = array();

	// Each request runs in its own PHP process, which loads WordPress and then the fixture with the spec in $spec.
	protected function admin_request( string $method, string $path, int $user_id, string $fixture, mixed $spec, array $post = array() ): array {
		$file  = (string) \strtok( $path, '?' );
		$query = array();
		\parse_str( (string) \parse_url( $path, \PHP_URL_QUERY ), $query );
		$result = (string) \tempnam( \sys_get_temp_dir(), 'acme-request-' );

		$process = \proc_open( array( \PHP_BINARY, __DIR__ . '/admin-request.php' ), array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open -- Runs the request in its own process.
		self::assertIsResource( $process );
		\fwrite(
			$pipes[0],
			\serialize( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- The request process reads it.
				array(
					'method'  => $method,
					'host'    => (string) \parse_url( \home_url(), \PHP_URL_HOST ) . ( \is_int( \parse_url( \home_url(), \PHP_URL_PORT ) ) ? ':' . \parse_url( \home_url(), \PHP_URL_PORT ) : '' ),
					'uri'     => '/wp-admin/' . $path,
					'file'    => $file,
					'get'     => $query,
					'post'    => $post,
					'cookies' => 0 === $user_id ? array() : $this->get_session_cookies( $user_id ),
					'fixture' => $fixture,
					'spec'    => $spec,
					'result'  => $result,
				)
			)
		);
		\fclose( $pipes[0] );
		$body   = (string) \stream_get_contents( $pipes[1] );
		$stderr = (string) \stream_get_contents( $pipes[2] );
		\fclose( $pipes[1] );
		\fclose( $pipes[2] );
		\proc_close( $process );

		$outcome = \unserialize( (string) \file_get_contents( $result ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- The request process wrote it.
		\unlink( $result );
		self::assertIsArray( $outcome, $stderr . $body );
		self::assertSame( array(), $outcome['errors'], $stderr );
		\wp_cache_flush();

		return $outcome + array( 'body' => $body );
	}

	protected function get_session_cookies( int $user_id ): array {
		if ( ! isset( $this->session_cookies[ $user_id ] ) ) {
			$expiration                        = \time() + \DAY_IN_SECONDS;
			$token                             = \WP_Session_Tokens::get_instance( $user_id )->create( $expiration );
			$this->session_tokens[ $user_id ]  = $token;
			$this->session_cookies[ $user_id ] = array(
				\AUTH_COOKIE      => \wp_generate_auth_cookie( $user_id, $expiration, 'auth', $token ),
				\LOGGED_IN_COOKIE => \wp_generate_auth_cookie( $user_id, $expiration, 'logged_in', $token ),
			);
		}

		return $this->session_cookies[ $user_id ];
	}

	#[After]
	protected function destroy_session_tokens(): void {
		foreach ( $this->session_tokens as $user_id => $token ) {
			\WP_Session_Tokens::get_instance( $user_id )->destroy( $token );
		}
	}
}
