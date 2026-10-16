<?php declare( strict_types=1 );
/**
 * Defines the requirements gate, which keeps a plugin from loading code its PHP or WordPress cannot run.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\Framework\Bootstrap
 */

namespace DeepWebSolutions\Framework\Bootstrap;

\defined( 'ABSPATH' ) || exit;

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
function check_requirements( string $plugin_file ): bool {
	$headers = \get_file_data(
		$plugin_file,
		array(
			'php'       => 'Requires PHP',
			'wordpress' => 'Requires at least',
		)
	);
	if ( \is_php_version_compatible( $headers['php'] ) && \is_wp_version_compatible( $headers['wordpress'] ) ) {
		return true;
	}

	\add_action(
		'all_admin_notices',
		static function () use ( $plugin_file ): void {
			if ( ! \current_user_can( 'activate_plugins' ) ) {
				return;
			}

			// Core translates the plugin's headers while it builds the message, so the message waits until the notice renders.
			$requirements = \validate_plugin_requirements( \plugin_basename( $plugin_file ) );
			if ( ! \is_wp_error( $requirements ) ) {
				return; // Core reads the headers from the plugins directory, so a plugin loaded from elsewhere reads as compatible.
			}

			if ( \function_exists( 'wp_admin_notice' ) ) {
				\wp_admin_notice(
					$requirements->get_error_message(),
					array(
						'type'           => 'error',
						'paragraph_wrap' => false, // Core's message is already paragraphs.
					)
				);
			} else {
				// The gate runs on WordPress from 5.2, and wp_admin_notice() arrived in 6.4.
				echo '<div class="notice notice-error">' . \wp_kses_post( $requirements->get_error_message() ) . '</div>';
			}
		}
	);

	return false;
}
