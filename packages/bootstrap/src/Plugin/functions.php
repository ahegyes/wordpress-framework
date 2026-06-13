<?php

namespace DeepWebSolutions\Framework\Bootstrap\Plugin;

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
 * @return  array{Name: string, PluginURI: string, Version: string, Description: string, Author: string, AuthorURI: string, TextDomain: string, DomainPath: string, Network: bool, RequiresWP: string, RequiresPHP: string, UpdateURI: string, RequiresPlugins: string, Title: string, AuthorName: string}
 */
function get_plugin_metadata( $plugin_basename, $translate = false ) {
	static $cache = array();

	$key = $translate ? 'translated' : 'raw';

	if ( ! isset( $cache[ $plugin_basename ][ $key ] ) ) {
		$plugin_file = WP_PLUGIN_DIR . '/' . $plugin_basename;

		if ( ! \is_readable( $plugin_file ) ) {
			// get_plugin_data() reads the file with file_get_contents(), which emits an
			// E_WARNING for a missing or unreadable plugin. Return the shape it produces
			// for a header-less file — including its slug-derived TextDomain fallback —
			// without the warning.
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
			if ( ! \function_exists( '\get_plugin_data' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$cache[ $plugin_basename ][ $key ] = \get_plugin_data( $plugin_file, false, $translate );
		}
	}

	return $cache[ $plugin_basename ][ $key ];
}
