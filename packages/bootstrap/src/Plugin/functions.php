<?php

namespace DeepWebSolutions\Framework\Bootstrap\Plugin;

/**
 * Returns the consumer plugin's metadata. When the main plugin file is not
 * readable, returns an empty-valued metadata shape whose TextDomain is guessed
 * from the plugin's directory name.
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
 * @return  array{Name: string, PluginURI: string, Version: string, Description: string, Author: string, AuthorURI: string, TextDomain: string, DomainPath: string, Network: bool, RequiresWP: string, RequiresPHP: string, UpdateURI: string, RequiresPlugins: string, Title: string, AuthorName: string}
 */
function get_plugin_metadata( $plugin_basename, $translate = false ) {
	static $cache = array();

	$key = $translate ? 'translated' : 'raw';

	if ( ! isset( $cache[ $plugin_basename ][ $key ] ) ) {
		$plugin_file = WP_PLUGIN_DIR . '/' . $plugin_basename;

		if ( ! \is_readable( $plugin_file ) ) {
			// A plugin's directory name conventionally matches its text domain, and consumers
			// derive the plugin slug from TextDomain — the guess keeps that derivation working
			// when the main file cannot be read.
			$text_domain = '';
			$plugin_slug = \dirname( $plugin_basename );
			if ( '.' !== $plugin_slug && false === \strpos( $plugin_slug, '/' ) ) {
				$text_domain = $plugin_slug;
			}

			$cache[ $plugin_basename ][ $key ] = array(
				'Name'            => '',
				'PluginURI'       => '',
				'Version'         => '',
				'Description'     => '',
				'Author'          => '',
				'AuthorURI'       => '',
				'TextDomain'      => $text_domain,
				'DomainPath'      => '',
				'Network'         => false,
				'RequiresWP'      => '',
				'RequiresPHP'     => '',
				'UpdateURI'       => '',
				'RequiresPlugins' => '',
				'Title'           => '',
				'AuthorName'      => '',
			);
		} else {
			if ( ! \function_exists( 'get_plugin_data' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$cache[ $plugin_basename ][ $key ] = \get_plugin_data( $plugin_file, false, $translate );
		}
	}

	return $cache[ $plugin_basename ][ $key ];
}
