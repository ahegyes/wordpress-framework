<?php declare( strict_types=1 );
/**
 * Runs one wp-admin request at global scope, as a web server would, and writes its outcome to the result file.
 *
 * WordPress's admin files read and write global variables at file scope, so they cannot run inside a test method.
 *
 * @package Acme
 */

$acme_request = unserialize( (string) stream_get_contents( STDIN ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- The test process writes it.
$acme_outcome = array(
	'location' => null,
	'died'     => null,
	'errors'   => array(),
	'records'  => array(),
);

register_shutdown_function(
	static function () use ( &$acme_outcome, $acme_request ): void {
		$fatal = error_get_last();
		if ( null !== $fatal && 0 !== ( $fatal['type'] & ( E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR ) ) ) {
			$acme_outcome['errors'][] = "{$fatal['message']} in {$fatal['file']}:{$fatal['line']}";
		}
		file_put_contents( $acme_request['result'], serialize( $acme_outcome ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- WordPress may not have loaded.
	}
);
set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Reports the request's warnings to the test.
	static function ( int $level, string $message, string $file, int $line ) use ( &$acme_outcome ): bool {
		if ( 0 === ( error_reporting() & $level ) ) { // phpcs:ignore WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_error_reporting, WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting -- Respects the @ operator.
			return false;
		}
		$acme_outcome['errors'][] = "$message in $file:$line";

		return true;
	}
);

$_SERVER = array_merge(
	$_SERVER,
	array(
		'REQUEST_METHOD'  => $acme_request['method'],
		'HTTP_HOST'       => $acme_request['host'],
		'SERVER_NAME'     => (string) parse_url( 'http://' . $acme_request['host'], PHP_URL_HOST ),
		'REMOTE_ADDR'     => '127.0.0.1',
		'HTTP_USER_AGENT' => 'Acme',
		'REQUEST_URI'     => $acme_request['uri'],
		'PHP_SELF'        => '/wp-admin/' . $acme_request['file'],
		'SCRIPT_NAME'     => '/wp-admin/' . $acme_request['file'],
	)
);

$_GET     = $acme_request['get'];
$_POST    = $acme_request['post']; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Sets the request's input.
$_COOKIE  = $acme_request['cookies'];
$_REQUEST = array_merge( $_GET, $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Sets the request's input.

// WordPress turns hooks set before it loads into WP_Hook objects.
$wp_filter = array( // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Hooks the request before WordPress loads.
	'muplugins_loaded' => array(
		10 => array(
			array(
				'function'      => static function () use ( $acme_request ): void {
					$spec = $acme_request['spec'];
					require $acme_request['fixture'];
				},
				'accepted_args' => 0,
			),
		),
	),
	'wp_redirect'      => array(
		PHP_INT_MAX => array(
			array(
				'function'      => static function ( string $location ) use ( &$acme_outcome ): string {
					$acme_outcome['location'] = $location;

					return $location;
				},
				'accepted_args' => 1,
			),
		),
	),
	'wp_die_handler'   => array(
		PHP_INT_MAX => array(
			array(
				'function'      => static function () use ( &$acme_outcome ): Closure {
					return static function ( mixed $message, mixed $title = '', mixed $args = array() ) use ( &$acme_outcome ): never {
						$acme_outcome['died'] = array(
							'message' => $message instanceof WP_Error ? $message->get_error_message() : (string) $message,
							'status'  => is_int( $args ) ? $args : (int) ( $args['response'] ?? 500 ),
						);
						exit;
					};
				},
				'accepted_args' => 0,
			),
		),
	),
);

// WooCommerce sends the first admin request within 30 seconds of its install to its setup wizard, as in CI's fresh environment.
$wp_filter['woocommerce_prevent_automatic_wizard_redirect'][10][] = array( // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Hooks the request before WordPress loads.
	'function'      => '__return_true',
	'accepted_args' => 0,
);

require dirname( __DIR__, 6 ) . '/wp-admin/' . $acme_request['file'];
