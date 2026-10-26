<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Bootstrap;

\defined( 'ABSPATH' ) || exit;

/**
 * Keeps a plugin from loading code its PHP or WordPress cannot run.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class Requirements {
	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	protected function __construct() {}

	// endregion

	// region METHODS

	/**
	 * Returns whether PHP and WordPress meet the plugin's Requires PHP and Requires at least headers, and otherwise shows WordPress core's requirements message to users who can activate plugins.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $plugin_file The path to the plugin's entry file.
	 *
	 * @return  bool
	 */
	public static function check_floors( string $plugin_file ): bool {
		$headers = get_file_data(
			$plugin_file,
			array(
				'php'       => 'Requires PHP',
				'wordpress' => 'Requires at least',
			)
		);
		if ( is_php_version_compatible( $headers['php'] ) && is_wp_version_compatible( $headers['wordpress'] ) ) {
			return true;
		}

		add_action(
			'all_admin_notices',
			static function () use ( $plugin_file ): void {
				// Core translates the plugin's headers while it builds the message, so the message waits until the notice renders.
				$requirements = validate_plugin_requirements( plugin_basename( $plugin_file ) );
				if ( ! is_wp_error( $requirements ) ) {
					return; // Core reads the headers from the plugins directory, so a plugin loaded from elsewhere reads as compatible.
				}

				self::print_admin_notice( $requirements );
			}
		);

		return false;
	}

	/**
	 * Prints an admin notice about the plugin to users who can activate plugins, as wp_admin_notice() prints it.
	 *
	 * The framework's boot checks report through it, because they run before the plugin's own admin notices exist or without them. An error prints its message without a paragraph wrap unless the arguments set one, since WordPress core's requirement messages are already paragraphs. Before WordPress 6.4, which has no wp_admin_notice(), it prints the same markup and honors only the type and paragraph_wrap arguments.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string|\WP_Error     $message The message, as HTML, or the error whose message to print.
	 * @param   array<string, mixed> $args    Optional. The notice's arguments, as wp_admin_notice() takes them. Default is an error notice.
	 * @phpstan-param array{type?: string, dismissible?: bool, id?: string, additional_classes?: array<string>, attributes?: array<string>, paragraph_wrap?: bool} $args
	 *
	 * @return  void
	 */
	public static function print_admin_notice( $message, array $args = array( 'type' => 'error' ) ): void {
		if ( is_wp_error( $message ) ) {
			$message = $message->get_error_message();
			$args   += array( 'paragraph_wrap' => false );
		}

		if ( ! \is_string( $message ) || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		if ( \function_exists( 'wp_admin_notice' ) ) {
			wp_admin_notice( $message, $args );
			return;
		}

		$type = isset( $args['type'] ) && '' !== $args['type'] ? " notice-{$args['type']}" : '';
		echo wp_kses_post( '<div class="notice' . esc_attr( $type ) . '">' . ( false === ( $args['paragraph_wrap'] ?? true ) ? $message : "<p>$message</p>" ) . '</div>' );
	}

	// endregion
}
