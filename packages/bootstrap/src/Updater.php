<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Bootstrap;

\defined( 'ABSPATH' ) || exit;

/**
 * Offers a plugin's GitHub releases through WordPress's own update screens.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class Updater {
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
	 * Offers the plugin's GitHub releases as updates, and answers its "View details" request, while its Update URI points at github.com.
	 *
	 * The Update URI is read whenever WordPress asks, so the updater answers nothing once it points elsewhere. A release that needs a newer WordPress is not offered, because WordPress's automatic updates check only the PHP requirement. WordPress asks for Update URI offers from version 5.8, so below it no update is offered.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $plugin_file The path to the plugin's entry file.
	 */
	public static function register( string $plugin_file ): void {
		$basename = \plugin_basename( $plugin_file );
		\add_filter(
			'update_plugins_github.com',
			static function ( $update, $plugin_data, $file ) use ( $plugin_file, $basename ) {
				if ( $basename !== $file || false !== $update ) {
					return $update;
				}

				$config  = self::get_updater_config( $plugin_file );
				$release = null === $config ? null : self::get_release( $config );
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

				$config = self::get_updater_config( $plugin_file );
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

				$release = self::get_release( $config );
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

	// endregion

	// region HELPERS

	/**
	 * Returns the plugin's updater configuration, after its configuration filter, with the plugin's identity, or null when its Update URI does not point at github.com, updates are off or the configuration is unusable.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $plugin_file The path to the plugin's entry file.
	 *
	 * @return  array{repository: string, asset: string, channel: 'stable'|'prerelease', slug: string, name: string, version: string, file: string}|null
	 */
	protected static function get_updater_config( string $plugin_file ): ?array {
		if ( ! \function_exists( 'get_plugin_data' ) ) {
			require_once \ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// WordPress reads a missing Text Domain as the plugin's folder name.
		$headers = \get_plugin_data( $plugin_file, false, false );
		$slug    = $headers['TextDomain'];
		// WordPress names the update hook after the host of the sanitized Update URI, which gains a scheme when it has none.
		$uri = \wp_parse_url( \esc_url_raw( $headers['UpdateURI'] ) );
		if ( '' === $slug || ! \is_array( $uri ) || 'github.com' !== ( $uri['host'] ?? '' ) ) {
			return null;
		}

		$defaults = array(
			'enabled'    => true,
			'repository' => \trim( $uri['path'] ?? '', '/' ),
			'asset'      => "$slug.zip",
			'channel'    => false === \strpos( $headers['Version'], '-' ) ? 'stable' : 'prerelease',
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
			'name'       => $headers['Name'],
			'version'    => $headers['Version'],
			'file'       => \basename( $plugin_file ),
		);
	}

	/**
	 * Returns the newest usable release on the configured channel, from the plugin's cache while it holds one, and otherwise from GitHub with the requirements of its tagged entry file.
	 *
	 * A usable release is cached for an hour, and a failed fetch or an unusable release as empty for five minutes, so update checks back off from a failing API. GitHub's release list is not ordered by version, so the prerelease channel takes the highest version among the ten it lists first, stable releases included.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array{repository: string, asset: string, channel: 'stable'|'prerelease', slug: string, name: string, version: string, file: string} $config The plugin's updater configuration.
	 *
	 * @return  array{version: string, tag: string, package: string, url: string, body: string, requires: string, requires_php: string}|null
	 */
	protected static function get_release( array $config ): ?array {
		$transient = "{$config['slug']}_github_release_{$config['channel']}";
		$cached    = \get_transient( $transient );
		if ( array() === $cached ) {
			return null;
		}

		$release = self::read_release( $cached );
		if ( null !== $release ) {
			return $release;
		}

		$body     = self::get_response_body( "https://api.github.com/repos/{$config['repository']}/" . ( 'prerelease' === $config['channel'] ? 'releases?per_page=10' : 'releases/latest' ) );
		$releases = null === $body ? null : \json_decode( $body, true );

		$newest  = array();
		$tag     = '';
		$version = '';
		foreach ( ( \is_array( $releases ) && 'prerelease' === $config['channel'] ) ? $releases : array( $releases ) as $candidate ) {
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

		$entry_file = null === $package ? null : self::get_response_body( "https://raw.githubusercontent.com/{$config['repository']}/" . \rawurlencode( $tag ) . "/{$config['file']}" );
		if ( null !== $entry_file ) {
			// WordPress's get_file_data() reads only paths, so the fetched entry file is parsed with the same pattern.
			$entry_file   = \str_replace( "\r", "\n", \substr( $entry_file, 0, 8 * \KB_IN_BYTES ) );
			$headers      = array(
				'requires'     => 'Requires at least',
				'requires_php' => 'Requires PHP',
			);
			$requirements = array();
			foreach ( $headers as $field => $header ) {
				$requirements[ $field ] = 1 === \preg_match( '/^(?:[ \t]*<\?(?:php)?)?[ \t\/*#@]*' . \preg_quote( $header, '/' ) . ':(.*)$/mi', $entry_file, $match ) ? \trim( (string) \preg_replace( '/\s*(?:\*\/|\?>).*/', '', $match[1] ) ) : '';
			}

			// WordPress prints the PHP requirement unescaped on its details screen, so anything but a version makes the release unusable.
			if ( \preg_grep( '/^(?:\d+(?:\.\d+)*)?$/', $requirements ) === $requirements ) {
				$release = self::read_release(
					array(
						'version' => $version,
						'tag'     => $tag,
						'package' => $package,
						'url'     => $newest['html_url'] ?? null,
						'body'    => $newest['body'] ?? '',
					) + $requirements
				);
			}
		}

		\set_transient( $transient, $release ?? array(), null === $release ? 5 * \MINUTE_IN_SECONDS : \HOUR_IN_SECONDS );

		return $release;
	}

	/**
	 * Returns the value as a release record, with an empty string for each missing or null field, or null when it has no package or a field of another type.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed $value The candidate record.
	 *
	 * @return  array{version: string, tag: string, package: string, url: string, body: string, requires: string, requires_php: string}|null
	 */
	protected static function read_release( $value ): ?array {
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
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $url The URL.
	 *
	 * @return  string|null
	 */
	protected static function get_response_body( string $url ): ?string {
		$response = \wp_remote_get( $url );
		if ( 200 !== \wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		return \wp_remote_retrieve_body( $response );
	}

	// endregion
}
