<?php
/**
 * Pre-autoload requirements check for consumer plugins.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\Framework\Bootstrap
 */

namespace DeepWebSolutions\Framework\Bootstrap;

/**
 * Minimum PHP version the framework itself requires.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
const FRAMEWORK_MIN_PHP = '8.5';

/**
 * Minimum WordPress version the framework itself requires.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
const FRAMEWORK_MIN_WP = '7.0';

/**
 * Returns whether the running PHP version meets the given minimum.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string $min_php Minimum PHP version required.
 *
 * @return  bool
 */
function is_php_compatible( $min_php ) {
	if ( ! \function_exists( '\is_php_version_compatible' ) ) {
		return false;
	}

	return \is_php_version_compatible( $min_php );
}

/**
 * Returns whether the running WordPress version meets the given minimum.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string $min_wp Minimum WordPress version required.
 *
 * @return  bool
 */
function is_wp_compatible( $min_wp ) {
	if ( ! \function_exists( '\is_wp_version_compatible' ) ) {
		return false;
	}

	return \is_wp_version_compatible( $min_wp );
}

/**
 * Returns the consumer plugin's metadata.
 *
 * Pass `$translate=true` ONLY from contexts that fire after `init` (e.g., an
 * `admin_notices` callback) to avoid WP 6.7+'s "doing it wrong" notice.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string $plugin_basename Plugin file path relative to plugins dir.
 * @param   bool   $translate       Whether to translate plugin headers.
 *
 * @return  array<string, string>
 */
function get_plugin_metadata( $plugin_basename, $translate = false ) {
	static $cache = array();

	$key = $translate ? 'translated' : 'raw';

	if ( ! isset( $cache[ $plugin_basename ][ $key ] ) ) {
		if ( ! \function_exists( '\get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$cache[ $plugin_basename ][ $key ] = \get_plugin_data(
			WP_PLUGIN_DIR . '/' . $plugin_basename,
			false,
			$translate
		);
	}

	return $cache[ $plugin_basename ][ $key ];
}

/**
 * Validates the consumer plugin's PHP/WP requirements with the framework's
 * own minimum applied as a floor.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string $plugin_basename Plugin file path relative to plugins dir.
 *
 * @return  true|\WP_Error
 */
function check_requirements( $plugin_basename ) {
	$metadata = namespace\get_plugin_metadata( $plugin_basename );

	$plugin_min_php = isset( $metadata['RequiresPHP'] ) && '' !== $metadata['RequiresPHP'] ? $metadata['RequiresPHP'] : '0';
	$plugin_min_wp  = isset( $metadata['RequiresWP'] ) && '' !== $metadata['RequiresWP'] ? $metadata['RequiresWP'] : '0';

	$effective_min_php = \version_compare( $plugin_min_php, namespace\FRAMEWORK_MIN_PHP, '>=' ) ? $plugin_min_php : namespace\FRAMEWORK_MIN_PHP;
	$effective_min_wp  = \version_compare( $plugin_min_wp, namespace\FRAMEWORK_MIN_WP, '>=' ) ? $plugin_min_wp : namespace\FRAMEWORK_MIN_WP;

	$errors = new \WP_Error();

	if ( ! namespace\is_php_compatible( $effective_min_php ) ) {
		$errors->add(
			'plugin_php_incompatible',
			'',
			array(
				'min'     => $effective_min_php,
				'current' => PHP_VERSION,
			)
		);
	}

	if ( ! namespace\is_wp_compatible( $effective_min_wp ) ) {
		$errors->add(
			'plugin_wp_incompatible',
			'',
			array(
				'min'     => $effective_min_wp,
				'current' => isset( $GLOBALS['wp_version'] ) ? $GLOBALS['wp_version'] : 'unknown',
			)
		);
	}

	return $errors->has_errors() ? $errors : true;
}

/**
 * Hooks an admin notice rendering the unmet-requirements `WP_Error`.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string    $plugin_basename Plugin file path relative to plugins dir.
 * @param   \WP_Error $error           Result returned by `check_requirements()`.
 *
 * @return  void
 */
function output_requirements_error( $plugin_basename, \WP_Error $error ) {
	\add_action(
		'admin_notices',
		function () use ( $plugin_basename, $error ) {
			$metadata = namespace\get_plugin_metadata( $plugin_basename, true );

			$intro = \sprintf(
				/* translators: 1: plugin name, 2: plugin version */
				\__( '<strong>%1$s (version %2$s)</strong> could not be initialized. Your environment does not meet all the requirements:', 'wp-framework-bootstrap' ),
				isset( $metadata['Name'] ) && '' !== $metadata['Name'] ? $metadata['Name'] : $plugin_basename,
				isset( $metadata['Version'] ) ? $metadata['Version'] : ''
			);

			$items = array();
			foreach ( $error->get_error_codes() as $code ) {
				$data    = \is_array( $error->get_error_data( $code ) ) ? $error->get_error_data( $code ) : array();
				$min     = isset( $data['min'] ) ? $data['min'] : '';
				$current = isset( $data['current'] ) ? $data['current'] : '';

				switch ( $code ) {
					case 'plugin_php_incompatible':
						$items[] = \sprintf(
							/* translators: 1: minimum PHP version, 2: current PHP version */
							\__( 'Requires PHP %1$s or higher; you are running %2$s.', 'wp-framework-bootstrap' ),
							$min,
							$current
						);
						break;
					case 'plugin_wp_incompatible':
						$items[] = \sprintf(
							/* translators: 1: minimum WordPress version, 2: current WordPress version */
							\__( 'Requires WordPress %1$s or higher; you are running %2$s.', 'wp-framework-bootstrap' ),
							$min,
							$current
						);
						break;
				}
			}

			$message = $intro . '<ul class="ul-disc"><li>' . \implode( '</li><li>', $items ) . '</li></ul>';
			if ( \function_exists( '\wp_admin_notice' ) ) {
				\wp_admin_notice( $message, array( 'type' => 'error' ) );
			} else {
				echo \wp_kses_post( '<div class="notice notice-error"><p>' . $message . '</p></div>' );
			}
		}
	);
}
