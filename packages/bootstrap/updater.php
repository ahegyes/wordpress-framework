<?php declare( strict_types=1 );
/**
 * Defines the GitHub release updater, which offers a plugin's GitHub releases through WordPress's own update screens.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\Framework\Bootstrap
 */

namespace DeepWebSolutions\Framework\Bootstrap;

\defined( 'ABSPATH' ) || exit;

/**
 * Offers the plugin's GitHub releases as updates, and answers its "View details" request, when its Update URI points at github.com.
 *
 * A release that needs a newer WordPress is not offered, because WordPress's automatic updates check only the PHP requirement. WordPress asks for Update URI offers from version 5.8, so below it no update is offered.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string $plugin_file The path to the plugin's entry file.
 */
function register_github_updater( string $plugin_file ): void {
	// WordPress names the update hook after the host of the sanitized Update URI, which gains a scheme when it has none.
	$headers = \get_file_data( $plugin_file, array( 'uri' => 'Update URI' ) );
	if ( 'github.com' !== \wp_parse_url( \esc_url_raw( $headers['uri'] ), \PHP_URL_HOST ) ) {
		return;
	}

	$basename = \plugin_basename( $plugin_file );
	\add_filter(
		'update_plugins_github.com',
		static function ( $update, $plugin_data, $file ) use ( $plugin_file, $basename ) {
			if ( $basename !== $file || false !== $update ) {
				return $update;
			}

			$config  = get_updater_config( $plugin_file );
			$release = null === $config ? null : get_release( $config );
			if ( null === $config || null === $release || ! \is_wp_version_compatible( $release['requires'] ) ) {
				return false;
			}

			return array(
				'slug'         => $config['slug'],
				'version'      => $release['version'],
				'url'          => $release['url'],
				'package'      => $release['package'],
				'requires'     => $release['requires'],
				'requires_php' => $release['requires_php'],
			);
		},
		10,
		3
	);

	\add_filter(
		'plugins_api',
		static function ( $result, $action, $args ) use ( $plugin_file ) {
			if ( false !== $result || 'plugin_information' !== $action || ! \is_object( $args ) ) {
				return $result;
			}

			$config = get_updater_config( $plugin_file );
			if ( null === $config || ( \get_object_vars( $args )['slug'] ?? null ) !== $config['slug'] ) {
				return $result;
			}

			// Without a release the answer stays local, since WordPress would describe whichever plugin holds the slug on wordpress.org.
			$information = array(
				'name'     => $config['name'],
				'slug'     => $config['slug'],
				'version'  => $config['version'],
				'sections' => array(), // WordPress's details screen reads the sections without a guard.
			);

			$release = get_release( $config );
			if ( null !== $release ) {
				$information = array(
					'version'       => $release['version'],
					'download_link' => $release['package'],
					'requires'      => $release['requires'],
					'requires_php'  => $release['requires_php'],
					'sections'      => array( 'changelog' => \nl2br( \esc_html( $release['body'] ) ) ),
				) + $information;
			}

			return (object) $information;
		},
		10,
		3
	);
}

/**
 * Returns the plugin's updater configuration, after its configuration filter, with the plugin's identity, or null when updates are off or the configuration is unusable.
 *
 * @internal
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string $plugin_file The path to the plugin's entry file.
 *
 * @return  array{repository: string, asset: string, channel: 'stable'|'prerelease', slug: string, name: string, version: string, file: string}|null
 */
function get_updater_config( string $plugin_file ): ?array {
	$headers = \get_file_data(
		$plugin_file,
		array(
			'name'    => 'Plugin Name',
			'version' => 'Version',
			'slug'    => 'Text Domain',
			'uri'     => 'Update URI',
		)
	);

	$slug = $headers['slug'];
	if ( '' === $slug ) {
		return null;
	}

	$defaults = array(
		'enabled'    => true,
		'repository' => \trim( (string) \wp_parse_url( \esc_url_raw( $headers['uri'] ), \PHP_URL_PATH ), '/' ),
		'asset'      => "$slug.zip",
		'channel'    => false === \strpos( $headers['version'], '-' ) ? 'stable' : 'prerelease',
	);

	/**
	 * Filters the GitHub updater's configuration for the plugin whose text domain ends the hook name; a key the filter leaves out keeps its default.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array{enabled: bool, repository: string, asset: string, channel: 'stable'|'prerelease'} $config          Whether updates are offered, the owner/name repository, the release asset's file name, and the channel: 'stable' follows the latest release, 'prerelease' the highest of the ten newest releases.
	 * @param   string                                                                                  $plugin_basename The plugin's basename.
	 */
	$config = \apply_filters( "dws_github_updater_config_$slug", $defaults, \plugin_basename( $plugin_file ) ); // @phpstan-ignore WPCompat.parameterNotAvailable.applyfilters.args (WordPress 6.0 only formalized the arguments that older versions already passed on.)
	if ( ! \is_array( $config ) ) {
		return null;
	}

	$config += $defaults;
	if ( true !== $config['enabled'] || ! \is_string( $config['repository'] ) || 1 !== \preg_match( '#^[\w.-]+/[\w.-]+$#D', $config['repository'] )
		|| ! \is_string( $config['asset'] ) || '' === $config['asset'] || ! \in_array( $config['channel'], array( 'stable', 'prerelease' ), true ) ) {
		return null;
	}

	return array(
		'repository' => $config['repository'],
		'asset'      => $config['asset'],
		'channel'    => $config['channel'],
		'slug'       => $slug,
		'name'       => $headers['name'],
		'version'    => $headers['version'],
		'file'       => \basename( $plugin_file ),
	);
}

/**
 * Returns the newest usable release on the configured channel, from the plugin's cache while it holds one.
 *
 * A usable release is cached for an hour, and a failed fetch or an unusable release as empty for five minutes, so update checks back off from a failing API.
 *
 * @internal
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   array{repository: string, asset: string, channel: 'stable'|'prerelease', slug: string, name: string, version: string, file: string} $config The plugin's updater configuration.
 *
 * @return  array{version: string, tag: string, package: string, url: string, body: string, requires: string, requires_php: string}|null
 */
function get_release( array $config ): ?array {
	// The uninstall engine deletes these names too.
	$transient = "{$config['slug']}_github_release_{$config['channel']}";
	$cached    = \get_transient( $transient );
	if ( array() === $cached ) {
		return null;
	}

	$release = read_release( $cached );
	if ( null === $release ) {
		$release = fetch_release( $config );
		\set_transient( $transient, $release ?? array(), null === $release ? 5 * \MINUTE_IN_SECONDS : \HOUR_IN_SECONDS );
	}

	return $release;
}

/**
 * Returns the newest release on the configured channel from GitHub, with the requirements of its tagged entry file, or null when no usable release can be read.
 *
 * GitHub's release list is not ordered by version, so the prerelease channel takes the highest version among the ten it lists first, stable releases included.
 *
 * @internal
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   array{repository: string, asset: string, channel: 'stable'|'prerelease', slug: string, name: string, version: string, file: string} $config The plugin's updater configuration.
 *
 * @return  array{version: string, tag: string, package: string, url: string, body: string, requires: string, requires_php: string}|null
 */
function fetch_release( array $config ): ?array {
	$body     = get_response_body( "https://api.github.com/repos/{$config['repository']}/" . ( 'prerelease' === $config['channel'] ? 'releases?per_page=10' : 'releases/latest' ) );
	$releases = null === $body ? null : \json_decode( $body, true );
	if ( ! \is_array( $releases ) ) {
		return null;
	}

	$newest  = array();
	$tag     = '';
	$version = '';
	foreach ( 'prerelease' === $config['channel'] ? $releases : array( $releases ) as $candidate ) {
		if ( ! \is_array( $candidate ) || true === ( $candidate['draft'] ?? false ) || ! \is_string( $candidate['tag_name'] ?? null ) ) {
			continue;
		}

		$candidate_version = \ltrim( $candidate['tag_name'], 'v' );
		// PHP's version_compare() ranks some words, such as 'preview', above every number.
		if ( 1 !== \preg_match( '/^\d+(?:\.\d+)*(?:-[0-9A-Za-z.]+)?$/', $candidate_version ) ) {
			continue;
		}

		if ( '' === $version || \version_compare( $candidate_version, $version, '>' ) ) {
			$newest  = $candidate;
			$tag     = $candidate['tag_name'];
			$version = $candidate_version;
		}
	}

	$package = null;
	foreach ( \is_array( $newest['assets'] ?? null ) ? $newest['assets'] : array() as $asset ) {
		if ( \is_array( $asset ) && ( $asset['name'] ?? null ) === $config['asset'] ) {
			$package = $asset['browser_download_url'] ?? null;
			break;
		}
	}

	$entry_file = null === $package ? null : get_response_body( "https://raw.githubusercontent.com/{$config['repository']}/" . \rawurlencode( $tag ) . "/{$config['file']}" );
	if ( null === $entry_file ) {
		return null;
	}

	// WordPress's get_file_data() reads only paths, so the fetched entry file is parsed with the same pattern.
	$entry_file   = \str_replace( "\r", "\n", \substr( $entry_file, 0, 8 * \KB_IN_BYTES ) );
	$headers      = array(
		'requires'     => 'Requires at least',
		'requires_php' => 'Requires PHP',
	);
	$requirements = array();
	foreach ( $headers as $field => $header ) {
		$requirements[ $field ] = 1 === \preg_match( '/^(?:[ \t]*<\?(?:php)?)?[ \t\/*#@]*' . \preg_quote( $header, '/' ) . ':(.*)$/mi', $entry_file, $match ) ? \trim( (string) \preg_replace( '/\s*(?:\*\/|\?>).*/', '', $match[1] ) ) : '';
		// WordPress prints the PHP requirement unescaped on its details screen, so anything but a version makes the release unusable.
		if ( '' !== $requirements[ $field ] && 1 !== \preg_match( '/^\d+(?:\.\d+)*$/', $requirements[ $field ] ) ) {
			return null;
		}
	}

	return read_release(
		array(
			'version' => $version,
			'tag'     => $tag,
			'package' => $package,
			'url'     => $newest['html_url'] ?? null,
			'body'    => $newest['body'] ?? '',
		) + $requirements
	);
}

/**
 * Returns the value as a release record, with an empty string for each missing or null field, or null when it has no package or a field of another type.
 *
 * @internal
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   mixed $value The candidate record.
 *
 * @return  array{version: string, tag: string, package: string, url: string, body: string, requires: string, requires_php: string}|null
 */
function read_release( $value ): ?array {
	if ( ! \is_array( $value ) ) {
		return null;
	}

	$release = array();
	foreach ( array( 'version', 'tag', 'package', 'url', 'body', 'requires', 'requires_php' ) as $field ) {
		$release[ $field ] = $value[ $field ] ?? '';
		if ( ! \is_string( $release[ $field ] ) ) {
			return null;
		}
	}

	return '' === $release['package'] ? null : $release;
}

/**
 * Returns the body of a GET request that answers 200, or null when the request fails or answers another status.
 *
 * @internal
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string $url The URL.
 *
 * @return  string|null
 */
function get_response_body( string $url ): ?string {
	$response = \wp_remote_get( $url );
	if ( 200 !== \wp_remote_retrieve_response_code( $response ) ) {
		return null;
	}

	return \wp_remote_retrieve_body( $response );
}
