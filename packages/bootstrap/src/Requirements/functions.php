<?php

namespace DeepWebSolutions\Framework\Bootstrap\Requirements;

use function DeepWebSolutions\Framework\Bootstrap\Environment\is_php_compatible;
use function DeepWebSolutions\Framework\Bootstrap\Environment\is_wp_compatible;
use function DeepWebSolutions\Framework\Bootstrap\Plugin\get_plugin_metadata;

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
	$metadata = get_plugin_metadata( $plugin_basename );

	$plugin_min_php = isset( $metadata['RequiresPHP'] ) && '' !== $metadata['RequiresPHP'] ? $metadata['RequiresPHP'] : '0';
	$plugin_min_wp  = isset( $metadata['RequiresWP'] ) && '' !== $metadata['RequiresWP'] ? $metadata['RequiresWP'] : '0';

	$effective_min_php = \version_compare( $plugin_min_php, namespace\FRAMEWORK_MIN_PHP, '>=' ) ? $plugin_min_php : namespace\FRAMEWORK_MIN_PHP;
	$effective_min_wp  = \version_compare( $plugin_min_wp, namespace\FRAMEWORK_MIN_WP, '>=' ) ? $plugin_min_wp : namespace\FRAMEWORK_MIN_WP;

	$errors = new \WP_Error();

	if ( ! is_php_compatible( $effective_min_php ) ) {
		$errors->add(
			'plugin_php_incompatible',
			'',
			array(
				'min'     => $effective_min_php,
				'current' => PHP_VERSION,
			)
		);
	}

	if ( ! is_wp_compatible( $effective_min_wp ) ) {
		$current_wp = \function_exists( 'wp_get_wp_version' ) ? \wp_get_wp_version() : ( isset( $GLOBALS['wp_version'] ) ? $GLOBALS['wp_version'] : 'unknown' );
		$errors->add(
			'plugin_wp_incompatible',
			'',
			array(
				'min'     => $effective_min_wp,
				'current' => $current_wp,
			)
		);
	}

	return $errors->has_errors() ? $errors : true;
}
