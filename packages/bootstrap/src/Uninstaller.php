<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Bootstrap;

\defined( 'ABSPATH' ) || exit;

/**
 * Removes a plugin's stored data where a site consents, and its scheduled jobs and cached releases in every case.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class Uninstaller {
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
	 * Removes the plugin's footprint from every site that consents, and unschedules its jobs and deletes its updater's cached releases on every site.
	 *
	 * On each site the cleanup runs first, and a failed cleanup keeps that site's footprint. Every operation runs even after another fails. A failed delete keeps that site's consent row, and the failures are thrown once the run ends, so WordPress keeps the plugin's files and the next uninstall retries. A job that cannot be unscheduled is logged instead, since it only ever fires into a missing callback, and a cached release that cannot be deleted is left to expire.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string               $plugin_file The path to the plugin's entry file.
	 * @param   array<string, mixed> $footprint   The plugin's stored data by kind.
	 * @param   array<string, mixed> $args        Optional. The consent (an option, its key in a grouped row, or a constant), the meta types the meta kinds may name beside post, term and comment, and the cleanup. Default none, which keeps the footprint.
	 * @phpstan-param array{consent?: array{option?: string, key?: string, constant?: string}, meta_types?: array<string, array{meta_type?: string, meta_tables?: list<string>, cache_groups?: list<string>}>, cleanup?: callable(array{scope: string, site_id: int, plugin_file: string}): void} $args
	 *
	 * @throws  \RuntimeException Thrown when a web request runs on a large network, before any change, or when a delete or the cleanup failed, after everything else ran.
	 */
	public static function run( string $plugin_file, array $footprint, array $args = array() ): void {
		// A web request could not finish sweeping a large network within the server's time limit, which WP-CLI does not have.
		if ( \is_multisite() && \wp_is_large_network() && ! ( \defined( 'WP_CLI' ) && true === \constant( 'WP_CLI' ) ) ) {
			throw new \RuntimeException( 'Uninstall does not sweep a large network in a web request; run `wp plugin uninstall` instead.' );
		}

		$consent    = $args['consent'] ?? array();
		$cleanup    = $args['cleanup'] ?? null;
		$meta_types = self::read_meta_types( $args['meta_types'] ?? array() );
		self::report_unreadable_keys( $plugin_file, $footprint, $meta_types );

		$failures = array();
		if ( \is_multisite() ) {
			$site_ids = \get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);
			foreach ( $site_ids as $site_id ) {
				\switch_to_blog( (int) $site_id );
				try {
					self::uninstall_site( $plugin_file, $footprint, $consent, $meta_types, $cleanup, $failures );
				} finally {
					\restore_current_blog();
					// WordPress keeps every visited site's options in its runtime cache, so the sweep's memory would grow with each site.
					if ( \function_exists( 'wp_cache_supports' ) && \function_exists( 'wp_cache_flush_runtime' ) && \wp_cache_supports( 'flush_runtime' ) ) {
						\wp_cache_flush_runtime();
					}
				}
			}

			if ( self::has_constant_consent( $consent ) && self::run_cleanup( $cleanup, 'network', $plugin_file, $failures ) ) {
				self::delete_global_user_meta( $footprint, $failures );
			}
		} else {
			self::uninstall_site( $plugin_file, $footprint, $consent, $meta_types, $cleanup, $failures );
		}

		if ( array() !== $failures ) {
			throw new \RuntimeException( 'Uninstall could not remove everything: ' . \implode( '; ', $failures ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing, never rendered.
		}
	}

	// endregion

	// region HELPERS

	/**
	 * Runs the uninstall pass on the current site, which runs the cleanup and deletes the footprint only where the site consents.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string                                                                                         $plugin_file The path to the plugin's entry file.
	 * @param   array<string, mixed>                                                                           $footprint   The plugin's stored data by kind.
	 * @param   array{option?: string, key?: string, constant?: string}                                        $consent     The consent argument.
	 * @param   array<string, array{meta_type: string, meta_tables: list<string>, cache_groups: list<string>}> $meta_types  The meta types by name.
	 * @param   callable|null                                                                                  $cleanup     The cleanup, if any.
	 * @param   list<string>                                                                                   $failures    The failures so far, to which this pass adds its own.
	 */
	protected static function uninstall_site( string $plugin_file, array $footprint, array $consent, array $meta_types, ?callable $cleanup, array &$failures ): void {
		$unscheduling = array();
		foreach ( self::get_strings( $footprint, 'cron_hooks' ) as $hook ) {
			// WordPress below 5.7 ignores the extra argument.
			self::attempt( "cron hook $hook", static fn () => \wp_unschedule_hook( $hook, true ), $unscheduling ); // @phpstan-ignore WPCompat.parameterNotAvailable.wpunschedulehook.wperror
		}

		// Action Scheduler is loaded only while a plugin that bundles it is active, and it reads a name of '0' as no name, which would cancel every plugin's pending actions.
		if ( \function_exists( 'as_unschedule_all_actions' ) ) {
			foreach ( \array_diff( self::get_strings( $footprint, 'as_hooks' ), array( '0' ) ) as $hook ) {
				self::attempt( "Action Scheduler hook $hook", static fn () => \as_unschedule_all_actions( $hook ), $unscheduling );
			}
			foreach ( \array_diff( self::get_strings( $footprint, 'as_groups' ), array( '0' ) ) as $group ) {
				self::attempt( "Action Scheduler group $group", static fn () => \as_unschedule_all_actions( '', array(), $group ), $unscheduling );
			}
		}

		if ( array() !== $unscheduling ) {
			\error_log( '[' . \plugin_basename( $plugin_file ) . '] Uninstall could not unschedule: ' . \implode( '; ', $unscheduling ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- No logger exists while a plugin is uninstalled.
		}

		// Release caches hold no site data and expire on their own, so they go whatever the consent, and one that cannot be deleted is left to expire.
		$slug = \get_file_data( $plugin_file, array( 'slug' => 'Text Domain' ) )['slug'];
		foreach ( array( 'stable', 'prerelease' ) as $channel ) {
			\delete_transient( "{$slug}_github_release_$channel" );
		}

		if ( ! self::has_constant_consent( $consent ) && ! self::has_stored_consent( $consent ) ) {
			return;
		}

		// A failed cleanup keeps the footprint, so its retry still finds every row it reads.
		$failed = \count( $failures );
		if ( ! self::run_cleanup( $cleanup, 'site', $plugin_file, $failures ) ) {
			return;
		}

		$consent_option = $consent['option'] ?? '';
		self::delete_site_data( $footprint, $consent_option, $meta_types, $failures );
		if ( ! \is_multisite() ) {
			self::delete_global_user_meta( $footprint, $failures );
		}

		// The consent row goes last and only after a clean pass, so an interrupted or failed run still consents on its retry.
		if ( '' !== $consent_option && \count( $failures ) === $failed ) {
			self::attempt( "option $consent_option", static fn () => \delete_option( $consent_option ), $failures );
		}
	}

	/**
	 * Deletes the footprint's per-site rows on the current site, except the consent row.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<string, mixed>                                                                           $footprint      The plugin's stored data by kind.
	 * @param   string                                                                                         $consent_option The consent row, which the caller deletes last.
	 * @param   array<string, array{meta_type: string, meta_tables: list<string>, cache_groups: list<string>}> $meta_types     The meta types by name.
	 * @param   list<string>                                                                                   $failures       The failures so far, to which the deletes add their own.
	 */
	protected static function delete_site_data( array $footprint, string $consent_option, array $meta_types, array &$failures ): void {
		$wpdb = self::get_wpdb();

		$options = self::get_strings( $footprint, 'options' );
		foreach ( self::get_strings( $footprint, 'option_prefixes' ) as $prefix ) {
			$options = \array_merge( $options, self::get_names_by_prefix( $wpdb->options, 'option_name', $prefix, $failures ) );
		}
		foreach ( \array_diff( \array_unique( $options ), array( $consent_option ) ) as $option ) {
			self::attempt( "option $option", static fn () => \delete_option( $option ), $failures );
		}

		foreach ( self::get_strings( $footprint, 'transients' ) as $transient ) {
			self::attempt( "transient $transient", static fn () => \delete_transient( $transient ), $failures );
		}

		foreach ( self::get_strings( $footprint, 'post_types' ) as $post_type ) {
			foreach ( self::get_matching_ids( $wpdb->posts, 'ID', 'post_type', $post_type, $failures ) as $post_id ) {
				self::attempt( "post $post_id of type $post_type", static fn () => self::confirm_deleted( \wp_delete_post( $post_id, true ) ), $failures );
			}
		}

		foreach ( self::get_strings( $footprint, 'comment_types' ) as $comment_type ) {
			foreach ( self::get_matching_ids( $wpdb->comments, 'comment_ID', 'comment_type', $comment_type, $failures ) as $comment_id ) {
				self::attempt( "comment $comment_id of type $comment_type", static fn () => self::confirm_deleted( \wp_delete_comment( $comment_id, true ) ), $failures );
			}
		}

		$meta_keys     = self::get_meta_map( $footprint, 'meta', $meta_types );
		$meta_prefixes = self::get_meta_map( $footprint, 'meta_prefixes', $meta_types );
		foreach ( \array_keys( $meta_keys + $meta_prefixes ) as $name ) {
			self::delete_meta( $meta_types[ $name ], $meta_keys[ $name ] ?? array(), $meta_prefixes[ $name ] ?? array(), $failures );
		}

		// Site user meta keys carry the site's table prefix, as update_user_option() writes them.
		$blog_prefix = $wpdb->get_blog_prefix();
		self::delete_meta_keys( 'user', \array_map( static fn ( string $key ): string => $blog_prefix . $key, self::get_strings( $footprint, 'user_meta_site' ) ), $failures );
		foreach ( self::get_strings( $footprint, 'user_meta_site_prefixes' ) as $prefix ) {
			self::delete_meta_keys( 'user', self::get_names_by_prefix( $wpdb->usermeta, 'meta_key', $blog_prefix . $prefix, $failures ), $failures );
		}

		foreach ( self::get_strings( $footprint, 'capabilities' ) as $capability ) {
			foreach ( \wp_roles()->role_objects as $role ) {
				self::attempt( "capability $capability of role $role->name", static fn () => $role->remove_cap( $capability ), $failures );
			}
		}

		foreach ( self::get_strings( $footprint, 'tables' ) as $table ) {
			$name = self::quote_identifier( $wpdb->prefix . $table );
			self::attempt( "table $table", static fn () => $wpdb->query( "DROP TABLE IF EXISTS $name" ), $failures ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Identifier placeholders need WordPress 6.2, so the name is quoted by hand.
		}
	}

	/**
	 * Deletes a meta type's rows under each key, and under each key that starts with one of the prefixes, from its WordPress meta table and from each of its own tables the current site holds, then flushes its cache groups.
	 *
	 * Rows match the key byte for byte, because the tables' collation would also match another plugin's key that differs only in letter case.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array{meta_type: string, meta_tables: list<string>, cache_groups: list<string>} $meta_type The meta type.
	 * @param   list<string>                                                                    $keys      The meta keys.
	 * @param   list<string>                                                                    $prefixes  The meta key prefixes.
	 * @param   list<string>                                                                    $failures  The failures so far, to which the deletes add their own.
	 */
	protected static function delete_meta( array $meta_type, array $keys, array $prefixes, array &$failures ): void {
		$wpdb = self::get_wpdb();

		$tables = array();
		foreach ( $meta_type['meta_tables'] as $table ) {
			if ( self::table_exists( $wpdb->prefix . $table, $failures ) ) {
				$tables[] = $wpdb->prefix . $table;
			}
		}

		$wordpress_tables = '' === $meta_type['meta_type'] ? array() : array( (string) \_get_meta_table( $meta_type['meta_type'] ) );
		foreach ( $prefixes as $prefix ) {
			foreach ( \array_merge( $wordpress_tables, $tables ) as $table ) {
				$keys = \array_merge( $keys, self::get_names_by_prefix( $table, 'meta_key', $prefix, $failures ) );
			}
		}
		$keys = \array_values( \array_unique( $keys ) );

		if ( '' !== $meta_type['meta_type'] ) {
			self::delete_meta_keys( $meta_type['meta_type'], $keys, $failures );
		}
		foreach ( $tables as $table ) {
			$name = self::quote_identifier( $table );
			foreach ( $keys as $key ) {
				// Identifier placeholders need WordPress 6.2, and WordPress 5.3 only formalized the variadic arguments that older versions already took.
				// @phpstan-ignore argument.type, argument.type, WPCompat.parameterNotAvailable.wpdbprepare.args
				self::attempt( "$table rows of key $key", static fn () => $wpdb->query( $wpdb->prepare( "DELETE FROM $name WHERE meta_key = %s AND CAST( meta_key AS BINARY ) = CAST( %s AS BINARY )", $key, $key ) ), $failures ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- A meta type's own tables have no WordPress meta API while their plugin is unloaded.
			}
		}

		// WordPress flushes a single group from 6.1, and where the object cache cannot, the entries expire on their own.
		if ( \function_exists( 'wp_cache_supports' ) && \function_exists( 'wp_cache_flush_group' ) && \wp_cache_supports( 'flush_group' ) ) {
			foreach ( $meta_type['cache_groups'] as $group ) {
				\wp_cache_flush_group( $group );
			}
		}
	}

	/**
	 * Deletes the footprint's network-wide user meta, which a single site keeps as its own.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<string, mixed> $footprint The plugin's stored data by kind.
	 * @param   list<string>         $failures  The failures so far, to which the deletes add their own.
	 */
	protected static function delete_global_user_meta( array $footprint, array &$failures ): void {
		$wpdb = self::get_wpdb();

		self::delete_meta_keys( 'user', self::get_strings( $footprint, 'user_meta_global' ), $failures );
		foreach ( self::get_strings( $footprint, 'user_meta_global_prefixes' ) as $prefix ) {
			self::delete_meta_keys( 'user', self::get_names_by_prefix( $wpdb->usermeta, 'meta_key', $prefix, $failures ), $failures );
		}
	}

	/**
	 * Deletes every row of each meta key, whatever object it belongs to, row by row.
	 *
	 * WordPress's delete_metadata() matches keys under the column's collation, which would also delete another plugin's key that differs only in letter case.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string       $meta_type The meta type.
	 * @param   list<string> $keys      The meta keys.
	 * @param   list<string> $failures  The failures so far, to which the deletes add their own.
	 */
	protected static function delete_meta_keys( string $meta_type, array $keys, array &$failures ): void {
		$table     = (string) \_get_meta_table( $meta_type );
		$id_column = 'user' === $meta_type ? 'umeta_id' : 'meta_id';
		foreach ( $keys as $key ) {
			foreach ( self::get_matching_ids( $table, $id_column, 'meta_key', $key, $failures ) as $meta_id ) {
				self::attempt( "$meta_type meta $meta_id of key $key", static fn () => self::confirm_deleted( \delete_metadata_by_mid( $meta_type, $meta_id ) ), $failures );
			}
		}
	}

	/**
	 * Runs the plugin's cleanup, if any, for one pass on the current site, and returns whether it did not fail.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   callable|null $cleanup     The cleanup, if any.
	 * @param   string        $scope       The pass's scope, 'site' or 'network'.
	 * @param   string        $plugin_file The path to the plugin's entry file.
	 * @param   list<string>  $failures    The failures so far, to which the cleanup's failure is added.
	 *
	 * @return  bool
	 */
	protected static function run_cleanup( ?callable $cleanup, string $scope, string $plugin_file, array &$failures ): bool {
		if ( null === $cleanup ) {
			return true;
		}

		$context = array(
			'scope'       => $scope,
			'site_id'     => \get_current_blog_id(),
			'plugin_file' => $plugin_file,
		);
		$failed  = \count( $failures );
		self::attempt( 'cleanup', static fn () => $cleanup( $context ), $failures );

		return \count( $failures ) === $failed;
	}

	/**
	 * Runs one operation and records its failure instead of stopping the pass.
	 *
	 * WordPress's delete functions return false both for a missing row and for a failed query, so only the database error tells a failure apart.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string       $subject   What the operation removes, for the failure's message.
	 * @param   \Closure     $operation The operation.
	 * @param   list<string> $failures  The failures so far, to which the operation's failure is added.
	 */
	protected static function attempt( string $subject, \Closure $operation, array &$failures ): void {
		$wpdb = self::get_wpdb();

		$wpdb->flush();
		try {
			$result = $operation();
		} catch ( \Throwable $throwable ) {
			$failures[] = "$subject: {$throwable->getMessage()}";
			return;
		}

		if ( $result instanceof \WP_Error ) {
			$failures[] = "$subject: {$result->get_error_message()}";
		} elseif ( '' !== $wpdb->last_error ) {
			$failures[] = "$subject: $wpdb->last_error";
		}
	}

	/**
	 * Throws when a WordPress delete that reports its outcome by its return value did not delete.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed $deleted The delete function's return value.
	 *
	 * @throws  \RuntimeException Thrown when the value is null or false.
	 */
	protected static function confirm_deleted( $deleted ): void {
		if ( null === $deleted || false === $deleted ) {
			throw new \RuntimeException( 'It was not deleted.' );
		}
	}

	/**
	 * Returns whether the consent constant is defined as true.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array{option?: string, key?: string, constant?: string} $consent The consent argument.
	 *
	 * @return  bool
	 */
	protected static function has_constant_consent( array $consent ): bool {
		$constant = $consent['constant'] ?? '';

		return '' !== $constant && \defined( $constant ) && true === \constant( $constant );
	}

	/**
	 * Returns whether the current site's consent option, or its key in a grouped row, holds the literal 'yes'.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array{option?: string, key?: string, constant?: string} $consent The consent argument.
	 *
	 * @return  bool
	 */
	protected static function has_stored_consent( array $consent ): bool {
		$option = $consent['option'] ?? '';
		if ( '' === $option ) {
			return false;
		}

		$value = \get_option( $option );
		$key   = $consent['key'] ?? '';
		if ( '' !== $key ) {
			$value = \is_array( $value ) && isset( $value[ $key ] ) ? $value[ $key ] : null;
		}

		return 'yes' === $value;
	}

	/**
	 * Logs, once, the footprint keys the engine does not read, or whose values it cannot read, since their data would otherwise stay without a trace.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string                                                                                         $plugin_file The path to the plugin's entry file.
	 * @param   array<string, mixed>                                                                           $footprint   The plugin's stored data by kind.
	 * @param   array<string, array{meta_type: string, meta_tables: list<string>, cache_groups: list<string>}> $meta_types  The meta types by name.
	 */
	protected static function report_unreadable_keys( string $plugin_file, array $footprint, array $meta_types ): void {
		$lists = array( 'cron_hooks', 'as_hooks', 'as_groups', 'options', 'option_prefixes', 'transients', 'post_types', 'comment_types', 'user_meta_site', 'user_meta_site_prefixes', 'user_meta_global', 'user_meta_global_prefixes', 'capabilities', 'tables' );
		$maps  = array( 'meta', 'meta_prefixes' );

		$unreadable = array();
		foreach ( $footprint as $key => $value ) {
			if ( \in_array( $key, $lists, true ) ) {
				$readable = null !== self::read_strings( $value );
			} elseif ( \in_array( $key, $maps, true ) ) {
				$readable = null !== self::read_meta_map( $value, $meta_types );
			} else {
				$readable = false;
			}

			if ( ! $readable ) {
				$unreadable[] = $key;
			}
		}

		if ( array() !== $unreadable ) {
			\error_log( '[' . \plugin_basename( $plugin_file ) . '] Uninstall skipped the footprint keys it cannot read: ' . \implode( ', ', $unreadable ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- No logger exists while a plugin is uninstalled.
		}
	}

	/**
	 * Returns the footprint's list under the key, or an empty list when the key is absent or unreadable.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<string, mixed> $footprint The plugin's stored data by kind.
	 * @param   string               $key       The footprint key.
	 *
	 * @return  list<string>
	 */
	protected static function get_strings( array $footprint, string $key ): array {
		return self::read_strings( $footprint[ $key ] ?? array() ) ?? array();
	}

	/**
	 * Returns the footprint's meta lists by meta type under the key, or none when the key is absent or unreadable.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<string, mixed>                                                                           $footprint  The plugin's stored data by kind.
	 * @param   string                                                                                         $key        The footprint key.
	 * @param   array<string, array{meta_type: string, meta_tables: list<string>, cache_groups: list<string>}> $meta_types The meta types by name.
	 *
	 * @return  array<string, list<string>>
	 */
	protected static function get_meta_map( array $footprint, string $key, array $meta_types ): array {
		return self::read_meta_map( $footprint[ $key ] ?? array(), $meta_types ) ?? array();
	}

	/**
	 * Returns the value as a list of non-empty strings, or null when it is anything else, since an empty prefix would match every row.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed $value The footprint value.
	 *
	 * @return  list<string>|null
	 */
	protected static function read_strings( $value ): ?array {
		if ( ! \is_array( $value ) ) {
			return null;
		}

		$strings = array();
		foreach ( $value as $item ) {
			if ( ! \is_string( $item ) || '' === $item ) {
				return null;
			}

			$strings[] = $item;
		}

		return $strings;
	}

	/**
	 * Returns the value as lists of strings by the name of a meta type, or null when it is anything else.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed                                                                                          $value      The footprint value.
	 * @param   array<string, array{meta_type: string, meta_tables: list<string>, cache_groups: list<string>}> $meta_types The meta types by name.
	 *
	 * @return  array<string, list<string>>|null
	 */
	protected static function read_meta_map( $value, array $meta_types ): ?array {
		if ( ! \is_array( $value ) ) {
			return null;
		}

		$map = array();
		foreach ( $value as $name => $keys ) {
			$keys = self::read_strings( $keys );
			if ( ! \is_string( $name ) || ! isset( $meta_types[ $name ] ) || null === $keys ) {
				return null;
			}

			$map[ $name ] = $keys;
		}

		return $map;
	}

	/**
	 * Returns WordPress's post, term and comment meta types, then each readable definition of the argument under another name, by name.
	 *
	 * A definition names the WordPress meta type its keys also live in, if any, its own tables without the site's table prefix, and the cache groups that hold its rows. User meta is left out, since on a network its rows belong to every site.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed $value The meta types argument.
	 *
	 * @return  array<string, array{meta_type: string, meta_tables: list<string>, cache_groups: list<string>}>
	 */
	protected static function read_meta_types( $value ): array {
		$meta_types = array();
		foreach ( array( 'post', 'term', 'comment' ) as $meta_type ) {
			$meta_types[ $meta_type ] = array(
				'meta_type'    => $meta_type,
				'meta_tables'  => array(),
				'cache_groups' => array(),
			);
		}

		foreach ( \is_array( $value ) ? $value : array() as $name => $definition ) {
			if ( ! \is_string( $name ) || isset( $meta_types[ $name ] ) || ! \is_array( $definition ) ) {
				continue;
			}

			$meta_type    = $definition['meta_type'] ?? '';
			$meta_tables  = self::read_strings( $definition['meta_tables'] ?? array() );
			$cache_groups = self::read_strings( $definition['cache_groups'] ?? array() );
			if ( \in_array( $meta_type, array( '', 'post', 'term', 'comment' ), true ) && null !== $meta_tables && null !== $cache_groups ) {
				$meta_types[ $name ] = array(
					'meta_type'    => $meta_type,
					'meta_tables'  => $meta_tables,
					'cache_groups' => $cache_groups,
				);
			}
		}

		return $meta_types;
	}

	/**
	 * Returns the column's distinct values that start with the prefix, and records a failed read.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string       $table    The table.
	 * @param   string       $column   The column.
	 * @param   string       $prefix   The prefix, matched byte for byte.
	 * @param   list<string> $failures The failures so far, to which a failed read is added.
	 *
	 * @return  list<string>
	 */
	protected static function get_names_by_prefix( string $table, string $column, string $prefix, array &$failures ): array {
		$wpdb = self::get_wpdb();

		$name = self::quote_identifier( $table );
		$wpdb->flush();
		// Identifier placeholders need WordPress 6.2, and WordPress 5.3 only formalized the variadic arguments that older versions already took.
		// @phpstan-ignore argument.type, WPCompat.parameterNotAvailable.wpdbprepare.args
		$names = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT CAST( `$column` AS BINARY ) FROM $name WHERE `$column` LIKE %s", $wpdb->esc_like( $prefix ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- WordPress lists no option or meta key by prefix.
		if ( '' !== $wpdb->last_error ) {
			$failures[] = "the names under $prefix in $table: $wpdb->last_error";
			return array();
		}

		// The collation also matches names that differ from the prefix in letter case or accents, and would fold such twins into one distinct name, hence the bytes.
		return \array_values( \array_filter( $names, static fn ( $name ): bool => \is_string( $name ) && 0 === \strpos( $name, $prefix ) ) );
	}

	/**
	 * Returns the ids of the rows whose column holds exactly the value, and records a failed read.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string       $table     The table.
	 * @param   string       $id_column The id column.
	 * @param   string       $column    The column to match.
	 * @param   string       $value     The value, matched byte for byte.
	 * @param   list<string> $failures  The failures so far, to which a failed read is added.
	 *
	 * @return  list<int>
	 */
	protected static function get_matching_ids( string $table, string $id_column, string $column, string $value, array &$failures ): array {
		$wpdb = self::get_wpdb();

		$name = self::quote_identifier( $table );
		$wpdb->flush();
		// Identifier placeholders need WordPress 6.2, and WordPress 5.3 only formalized the variadic arguments that older versions already took.
		// @phpstan-ignore argument.type, WPCompat.parameterNotAvailable.wpdbprepare.args
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT `$id_column` AS id, `$column` AS value FROM $name WHERE `$column` = %s", $value ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Reads every row, trashed posts and auto-drafts included.
		if ( '' !== $wpdb->last_error ) {
			$failures[] = "the $value rows of $table: $wpdb->last_error";
			return array();
		}

		$ids = array();
		foreach ( (array) $rows as $row ) {
			// The column's collation also matches values that differ in letter case or accents.
			if ( \is_array( $row ) && ( $row['value'] ?? null ) === $value && \is_numeric( $row['id'] ?? null ) ) {
				$ids[] = (int) $row['id'];
			}
		}

		return $ids;
	}

	/**
	 * Returns whether the current site's database holds the table, and records a failed read.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string       $table    The table.
	 * @param   list<string> $failures The failures so far, to which a failed read is added.
	 *
	 * @return  bool
	 */
	protected static function table_exists( string $table, array &$failures ): bool {
		$wpdb = self::get_wpdb();

		$wpdb->flush();
		// WordPress 5.3 only formalized the variadic arguments that older versions already took.
		// @phpstan-ignore WPCompat.parameterNotAvailable.wpdbprepare.args
		$name = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- A schema read.
		if ( '' !== $wpdb->last_error ) {
			$failures[] = "the table $table: $wpdb->last_error";
			return false;
		}

		return $table === $name;
	}

	/**
	 * Returns the table name quoted as a MySQL identifier.
	 *
	 * Identifier placeholders need WordPress 6.2, so names are quoted by hand.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $name The table name.
	 *
	 * @return  string
	 */
	protected static function quote_identifier( string $name ): string {
		return '`' . \str_replace( '`', '``', $name ) . '`';
	}

	/**
	 * Returns WordPress's database connection.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @throws  \LogicException Thrown when WordPress has none.
	 *
	 * @return  \wpdb
	 */
	protected static function get_wpdb(): \wpdb {
		$wpdb = $GLOBALS['wpdb'] ?? null;
		if ( ! $wpdb instanceof \wpdb ) {
			throw new \LogicException( 'The uninstall engine needs a wpdb connection.' );
		}

		return $wpdb;
	}

	// endregion
}
