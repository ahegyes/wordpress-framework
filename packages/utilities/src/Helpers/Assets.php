<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Helpers;

/**
 * Asset-path helpers for framework consumers.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class Assets {
	// region METHODS

	/**
	 * Returns the .min variant of an asset path when script debugging is off and that file exists.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $path Asset path.
	 *
	 * @return  string
	 */
	public static function minified_path( string $path ): string {
		if ( self::is_script_debug_on() || self::has_minified_suffix( $path ) ) {
			return $path;
		}

		$minified_path = self::add_minified_suffix( $path );

		return \is_file( $minified_path ) ? $minified_path : $path;
	}

	/**
	 * Returns a filemtime-based asset version, or the supplied fallback when the file cannot be read.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $path             Asset path.
	 * @param   string $fallback_version Version returned when no modified time can be resolved.
	 *
	 * @return  string
	 */
	public static function version( string $path, string $fallback_version = '' ): string {
		$modified_time = \is_file( $path ) ? \filemtime( $path ) : false;

		return false !== $modified_time ? (string) $modified_time : $fallback_version;
	}

	// endregion

	// region HELPERS

	/**
	 * Whether WordPress is configured to load unminified script assets.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  bool
	 */
	protected static function is_script_debug_on(): bool {
		return \defined( 'SCRIPT_DEBUG' ) && true === \constant( 'SCRIPT_DEBUG' );
	}

	/**
	 * Whether a path already points to a .min asset.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $path Asset path.
	 *
	 * @return  bool
	 */
	protected static function has_minified_suffix( string $path ): bool {
		$filename = \pathinfo( $path, PATHINFO_FILENAME );

		return \str_ends_with( $filename, '.min' );
	}

	/**
	 * Adds the .min suffix before an extension, or at the end of an extensionless path.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $path Asset path.
	 *
	 * @return  string
	 */
	protected static function add_minified_suffix( string $path ): string {
		$directory = \pathinfo( $path, PATHINFO_DIRNAME );
		$filename  = \pathinfo( $path, PATHINFO_FILENAME );
		$extension = \pathinfo( $path, PATHINFO_EXTENSION );
		$suffix    = '' === $extension ? '.min' : '.min.' . $extension;
		$basename  = $filename . $suffix;

		return '.' === $directory ? $basename : $directory . DIRECTORY_SEPARATOR . $basename;
	}

	// endregion
}
