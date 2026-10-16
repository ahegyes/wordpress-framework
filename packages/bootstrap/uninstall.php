<?php declare( strict_types=1 );
/**
 * Defines the uninstall engine, which removes a plugin's stored data where a site consents, and its scheduled jobs and cached releases in every case.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\Framework\Bootstrap
 */

namespace DeepWebSolutions\Framework\Bootstrap;

\defined( 'ABSPATH' ) || exit;

/**
 * Removes the plugin's footprint from the current site, or with the network flag from every site of a network that is not large, wherever the site consents, and unschedules its jobs and deletes its updater's cached releases on every site it visits.
 *
 * Every operation runs even after another fails. A failed delete of the footprint keeps that site's consent row, and the failed deletes and callables are thrown once the run ends, so WordPress keeps the plugin's files and the next uninstall retries. A job that cannot be unscheduled is logged instead, since it only ever fires into a missing callback.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string               $plugin_file The path to the plugin's entry file.
 * @param   array<string, mixed> $footprint   The plugin's stored data by kind, under the engine's keys and the handlers' keys.
 * @param   array<string, mixed> $args        Optional. The consent (an option, its key in a grouped row, or a constant), the handlers by footprint key, the cleanup and the network flag. Default none, which keeps every row.
 * @phpstan-param array{consent?: array{option?: string, key?: string, constant?: string}, handlers?: array<string, callable(mixed, array{scope: string, site_id: int, plugin_file: string}): void>, cleanup?: callable(array{scope: string, site_id: int, plugin_file: string}): void, network?: bool} $args
 *
 * @throws  \RuntimeException Thrown when a delete, a handler or the cleanup failed, after everything else ran.
 */
function uninstall( string $plugin_file, array $footprint, array $args = array() ): void {
	$consent  = $args['consent'] ?? array();
	$handlers = $args['handlers'] ?? array();
	$cleanup  = $args['cleanup'] ?? null;
	report_unreadable_keys( $plugin_file, $footprint, $handlers );

	$failures = array();
	$network  = true === ( $args['network'] ?? false ) && \is_multisite();
	if ( $network && \wp_is_large_network() ) {
		$network = false;
		\error_log( '[' . \plugin_basename( $plugin_file ) . '] Uninstall cleaned only the current site, because the network is large.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- No logger exists while a plugin is uninstalled.
	}

	if ( $network ) {
		$site_ids = \get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		);
		foreach ( $site_ids as $site_id ) {
			\switch_to_blog( (int) $site_id );
			try {
				uninstall_site( $plugin_file, $footprint, $consent, $handlers, $cleanup, $failures );
			} finally {
				\restore_current_blog();
			}
		}

		if ( has_constant_consent( $consent ) ) {
			delete_global_user_meta( $footprint, $failures );
			run_callables( $footprint, $handlers, $cleanup, get_context( 'network', $plugin_file ), $failures );
		}
	} else {
		uninstall_site( $plugin_file, $footprint, $consent, $handlers, $cleanup, $failures );
	}

	if ( array() !== $failures ) {
		throw new \RuntimeException( 'Uninstall could not remove everything: ' . \implode( '; ', $failures ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing, never rendered.
	}
}

/**
 * Runs the uninstall pass on the current site, which deletes the footprint only where the site consents.
 *
 * @internal
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string                                                  $plugin_file The path to the plugin's entry file.
 * @param   array<string, mixed>                                    $footprint   The plugin's stored data by kind.
 * @param   array{option?: string, key?: string, constant?: string} $consent     The consent argument.
 * @param   array<string, callable>                                 $handlers    The handlers by footprint key.
 * @param   callable|null                                           $cleanup     The cleanup, if any.
 * @param   list<string>                                            $failures    The failures so far, to which this pass adds its own.
 */
function uninstall_site( string $plugin_file, array $footprint, array $consent, array $handlers, ?callable $cleanup, array &$failures ): void {
	$unscheduling = array();
	foreach ( get_strings( $footprint, 'cron_hooks' ) as $hook ) {
		// WordPress below 5.7 ignores the extra argument.
		attempt( "cron hook $hook", static fn () => \wp_unschedule_hook( $hook, true ), $unscheduling ); // @phpstan-ignore WPCompat.parameterNotAvailable.wpunschedulehook.wperror
	}

	// Action Scheduler is loaded only while a plugin that bundles it is active.
	if ( \function_exists( 'as_unschedule_all_actions' ) ) {
		foreach ( get_strings( $footprint, 'as_hooks' ) as $hook ) {
			attempt( "Action Scheduler hook $hook", static fn () => \as_unschedule_all_actions( $hook ), $unscheduling );
		}
		foreach ( get_strings( $footprint, 'as_groups' ) as $group ) {
			attempt( "Action Scheduler group $group", static fn () => \as_unschedule_all_actions( '', array(), $group ), $unscheduling );
		}
	}

	if ( array() !== $unscheduling ) {
		\error_log( '[' . \plugin_basename( $plugin_file ) . '] Uninstall could not unschedule: ' . \implode( '; ', $unscheduling ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- No logger exists while a plugin is uninstalled.
	}

	// The updater caches each channel's release under the plugin's text domain.
	$slug = \get_file_data( $plugin_file, array( 'slug' => 'Text Domain' ) )['slug'];
	foreach ( array( 'stable', 'prerelease' ) as $channel ) {
		attempt( "transient {$slug}_github_release_$channel", static fn () => \delete_transient( "{$slug}_github_release_$channel" ), $failures );
	}

	if ( ! has_constant_consent( $consent ) && ! has_stored_consent( $consent ) ) {
		return;
	}

	$failed         = \count( $failures );
	$consent_option = $consent['option'] ?? '';
	delete_site_data( $footprint, $consent_option, $failures );
	if ( ! \is_multisite() ) {
		delete_global_user_meta( $footprint, $failures );
	}
	run_callables( $footprint, $handlers, $cleanup, get_context( 'site', $plugin_file ), $failures );

	// The consent row goes last and only after a clean pass, so an interrupted or failed run still consents on its retry.
	if ( '' !== $consent_option && \count( $failures ) === $failed ) {
		attempt( "option $consent_option", static fn () => \delete_option( $consent_option ), $failures );
	}
}

/**
 * Deletes the footprint's per-site rows on the current site, except the consent row and the options of pages that stay.
 *
 * @internal
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   array<string, mixed> $footprint      The plugin's stored data by kind.
 * @param   string               $consent_option The consent row, which the caller deletes last.
 * @param   list<string>         $failures       The failures so far, to which the deletes add their own.
 */
function delete_site_data( array $footprint, string $consent_option, array &$failures ): void {
	$wpdb = get_wpdb();

	// A page option is read before any option is deleted, and a page that stays keeps its option, so the retry still finds the page.
	$kept = array( $consent_option );
	foreach ( get_strings( $footprint, 'page_options' ) as $option ) {
		$page_id = \get_option( $option );
		// An id of 0 would make WordPress read the global post.
		if ( \is_numeric( $page_id ) && 0 < (int) $page_id && 'page' === \get_post_type( (int) $page_id ) ) {
			$failed = \count( $failures );
			attempt( "page $page_id of option $option", static fn () => confirm_deleted( \wp_delete_post( (int) $page_id, true ) ), $failures );
			if ( \count( $failures ) > $failed ) {
				$kept[] = $option;
			}
		}
	}

	$options = \array_merge( get_strings( $footprint, 'page_options' ), get_strings( $footprint, 'options' ) );
	foreach ( get_strings( $footprint, 'option_prefixes' ) as $prefix ) {
		$options = \array_merge( $options, get_names_by_prefix( $wpdb->options, 'option_name', $prefix, $failures ) );
	}
	foreach ( \array_diff( \array_unique( $options ), $kept ) as $option ) {
		attempt( "option $option", static fn () => \delete_option( $option ), $failures );
	}

	foreach ( get_strings( $footprint, 'transients' ) as $transient ) {
		attempt( "transient $transient", static fn () => \delete_transient( $transient ), $failures );
	}

	foreach ( get_strings( $footprint, 'post_types' ) as $post_type ) {
		foreach ( get_matching_ids( $wpdb->posts, 'ID', 'post_type', $post_type, $failures ) as $post_id ) {
			attempt( "post $post_id of type $post_type", static fn () => confirm_deleted( \wp_delete_post( $post_id, true ) ), $failures );
		}
	}

	foreach ( get_strings( $footprint, 'comment_types' ) as $comment_type ) {
		foreach ( get_matching_ids( $wpdb->comments, 'comment_ID', 'comment_type', $comment_type, $failures ) as $comment_id ) {
			attempt( "comment $comment_id of type $comment_type", static fn () => confirm_deleted( \wp_delete_comment( $comment_id, true ) ), $failures );
		}
	}

	foreach ( get_meta_map( $footprint, 'meta' ) as $meta_type => $keys ) {
		delete_meta_keys( $meta_type, $keys, $failures );
	}
	foreach ( get_meta_map( $footprint, 'meta_prefixes' ) as $meta_type => $prefixes ) {
		foreach ( $prefixes as $prefix ) {
			delete_meta_keys( $meta_type, get_names_by_prefix( (string) \_get_meta_table( $meta_type ), 'meta_key', $prefix, $failures ), $failures );
		}
	}

	// Site user meta keys carry the site's table prefix, as update_user_option() writes them.
	$blog_prefix = $wpdb->get_blog_prefix();
	delete_meta_keys( 'user', \array_map( static fn ( string $key ): string => $blog_prefix . $key, get_strings( $footprint, 'user_meta_site' ) ), $failures );
	foreach ( get_strings( $footprint, 'user_meta_site_prefixes' ) as $prefix ) {
		delete_meta_keys( 'user', get_names_by_prefix( $wpdb->usermeta, 'meta_key', $blog_prefix . $prefix, $failures ), $failures );
	}

	foreach ( get_strings( $footprint, 'capabilities' ) as $capability ) {
		foreach ( \wp_roles()->role_objects as $role ) {
			attempt( "capability $capability of role $role->name", static fn () => $role->remove_cap( $capability ), $failures );
		}
	}

	foreach ( get_strings( $footprint, 'tables' ) as $table ) {
		$name = \str_replace( '`', '``', $wpdb->prefix . $table );
		attempt( "table $table", static fn () => $wpdb->query( "DROP TABLE IF EXISTS `$name`" ), $failures ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Identifier placeholders need WordPress 6.2, so the name is quoted by hand.
	}
}

/**
 * Deletes the footprint's network-wide user meta, which a single site keeps as its own.
 *
 * @internal
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   array<string, mixed> $footprint The plugin's stored data by kind.
 * @param   list<string>         $failures  The failures so far, to which the deletes add their own.
 */
function delete_global_user_meta( array $footprint, array &$failures ): void {
	$wpdb = get_wpdb();

	delete_meta_keys( 'user', get_strings( $footprint, 'user_meta_global' ), $failures );
	foreach ( get_strings( $footprint, 'user_meta_global_prefixes' ) as $prefix ) {
		delete_meta_keys( 'user', get_names_by_prefix( $wpdb->usermeta, 'meta_key', $prefix, $failures ), $failures );
	}
}

/**
 * Deletes every row of each meta key, whatever object it belongs to, row by row.
 *
 * WordPress's delete_metadata() matches keys under the column's collation, which would also delete another plugin's key that differs only in letter case.
 *
 * @internal
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string       $meta_type The meta type.
 * @param   list<string> $keys      The meta keys.
 * @param   list<string> $failures  The failures so far, to which the deletes add their own.
 */
function delete_meta_keys( string $meta_type, array $keys, array &$failures ): void {
	$table     = (string) \_get_meta_table( $meta_type );
	$id_column = 'user' === $meta_type ? 'umeta_id' : 'meta_id';
	foreach ( $keys as $key ) {
		foreach ( get_matching_ids( $table, $id_column, 'meta_key', $key, $failures ) as $meta_id ) {
			attempt( "$meta_type meta $meta_id of key $key", static fn () => confirm_deleted( \delete_metadata_by_mid( $meta_type, $meta_id ) ), $failures );
		}
	}
}

/**
 * Runs the plugin's handlers and cleanup for one pass.
 *
 * @internal
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   array<string, mixed>                                    $footprint The plugin's stored data by kind.
 * @param   array<string, callable>                                 $handlers  The handlers by footprint key.
 * @param   callable|null                                           $cleanup   The cleanup, if any.
 * @param   array{scope: string, site_id: int, plugin_file: string} $context   The pass's context.
 * @param   list<string>                                            $failures  The failures so far, to which the callables add their own.
 */
function run_callables( array $footprint, array $handlers, ?callable $cleanup, array $context, array &$failures ): void {
	foreach ( $handlers as $key => $handler ) {
		if ( \array_key_exists( $key, $footprint ) ) {
			$values = $footprint[ $key ];
			attempt( "handler $key", static fn () => $handler( $values, $context ), $failures );
		}
	}

	if ( null !== $cleanup ) {
		attempt( 'cleanup', static fn () => $cleanup( $context ), $failures );
	}
}

/**
 * Runs one operation and records its failure instead of stopping the pass.
 *
 * WordPress's delete functions return false both for a missing row and for a failed query, so only the database error tells a failure apart.
 *
 * @internal
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string       $subject   What the operation removes, for the failure's message.
 * @param   \Closure     $operation The operation.
 * @param   list<string> $failures  The failures so far, to which the operation's failure is added.
 */
function attempt( string $subject, \Closure $operation, array &$failures ): void {
	$wpdb = get_wpdb();

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
 * @internal
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   mixed $deleted The delete function's return value.
 *
 * @throws  \RuntimeException Thrown when the value is null or false.
 */
function confirm_deleted( $deleted ): void {
	if ( null === $deleted || false === $deleted ) {
		throw new \RuntimeException( 'It was not deleted.' );
	}
}

/**
 * Returns whether the consent constant is defined as true.
 *
 * @internal
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   array{option?: string, key?: string, constant?: string} $consent The consent argument.
 *
 * @return  bool
 */
function has_constant_consent( array $consent ): bool {
	$constant = $consent['constant'] ?? '';

	return '' !== $constant && \defined( $constant ) && true === \constant( $constant );
}

/**
 * Returns whether the current site's consent option, or its key in a grouped row, holds the literal 'yes'.
 *
 * @internal
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   array{option?: string, key?: string, constant?: string} $consent The consent argument.
 *
 * @return  bool
 */
function has_stored_consent( array $consent ): bool {
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
 * Returns the context a handler and the cleanup receive for a pass on the current site.
 *
 * @internal
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string $scope       The pass's scope, 'site' or 'network'.
 * @param   string $plugin_file The path to the plugin's entry file.
 *
 * @return  array{scope: string, site_id: int, plugin_file: string}
 */
function get_context( string $scope, string $plugin_file ): array {
	return array(
		'scope'       => $scope,
		'site_id'     => \get_current_blog_id(),
		'plugin_file' => $plugin_file,
	);
}

/**
 * Logs, once, the footprint keys that neither the engine nor a handler reads, or whose values the engine cannot read, since their data would otherwise stay without a trace.
 *
 * @internal
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string               $plugin_file The path to the plugin's entry file.
 * @param   array<string, mixed> $footprint   The plugin's stored data by kind.
 * @param   array<string, mixed> $handlers    The handlers by footprint key.
 */
function report_unreadable_keys( string $plugin_file, array $footprint, array $handlers ): void {
	$lists = array( 'cron_hooks', 'as_hooks', 'as_groups', 'options', 'option_prefixes', 'transients', 'post_types', 'page_options', 'comment_types', 'user_meta_site', 'user_meta_site_prefixes', 'user_meta_global', 'user_meta_global_prefixes', 'capabilities', 'tables' );
	$maps  = array( 'meta', 'meta_prefixes' );

	$unreadable = array();
	foreach ( $footprint as $key => $value ) {
		if ( \in_array( $key, $lists, true ) ) {
			$readable = null !== read_strings( $value );
		} elseif ( \in_array( $key, $maps, true ) ) {
			$readable = null !== read_meta_map( $value );
		} else {
			$readable = \array_key_exists( $key, $handlers );
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
 * @internal
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   array<string, mixed> $footprint The plugin's stored data by kind.
 * @param   string               $key       The footprint key.
 *
 * @return  list<string>
 */
function get_strings( array $footprint, string $key ): array {
	return read_strings( $footprint[ $key ] ?? array() ) ?? array();
}

/**
 * Returns the footprint's meta lists by meta type under the key, or none when the key is absent or unreadable.
 *
 * @internal
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   array<string, mixed> $footprint The plugin's stored data by kind.
 * @param   string               $key       The footprint key.
 *
 * @return  array<string, list<string>>
 */
function get_meta_map( array $footprint, string $key ): array {
	return read_meta_map( $footprint[ $key ] ?? array() ) ?? array();
}

/**
 * Returns the value as a list of non-empty strings, or null when it is anything else, since an empty prefix would match every row.
 *
 * @internal
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   mixed $value The footprint value.
 *
 * @return  list<string>|null
 */
function read_strings( $value ): ?array {
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
 * Returns the value as lists of strings by post, term or comment meta type, or null when it is anything else.
 *
 * @internal
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   mixed $value The footprint value.
 *
 * @return  array<string, list<string>>|null
 */
function read_meta_map( $value ): ?array {
	if ( ! \is_array( $value ) ) {
		return null;
	}

	$map = array();
	foreach ( $value as $meta_type => $keys ) {
		$keys = read_strings( $keys );
		if ( ! \in_array( $meta_type, array( 'post', 'term', 'comment' ), true ) || null === $keys ) {
			return null;
		}

		$map[ $meta_type ] = $keys;
	}

	return $map;
}

/**
 * Returns the column's distinct values that start with the prefix, and records a failed read.
 *
 * @internal
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
function get_names_by_prefix( string $table, string $column, string $prefix, array &$failures ): array {
	$wpdb = get_wpdb();

	$wpdb->flush();
	// Identifier placeholders need WordPress 6.2, and WordPress 5.3 only formalized the variadic arguments that older versions already took.
	// @phpstan-ignore argument.type, WPCompat.parameterNotAvailable.wpdbprepare.args
	$names = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT CAST( `$column` AS BINARY ) FROM `$table` WHERE `$column` LIKE %s", $wpdb->esc_like( $prefix ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- WordPress lists no option or meta key by prefix.
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
 * @internal
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
function get_matching_ids( string $table, string $id_column, string $column, string $value, array &$failures ): array {
	$wpdb = get_wpdb();

	$wpdb->flush();
	// Identifier placeholders need WordPress 6.2, and WordPress 5.3 only formalized the variadic arguments that older versions already took.
	// @phpstan-ignore argument.type, WPCompat.parameterNotAvailable.wpdbprepare.args
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT `$id_column` AS id, `$column` AS value FROM `$table` WHERE `$column` = %s", $value ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Reads every row, trashed posts and auto-drafts included.
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
 * Returns WordPress's database connection.
 *
 * @internal
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @throws  \LogicException Thrown when WordPress has none.
 *
 * @return  \wpdb
 */
function get_wpdb(): \wpdb {
	$wpdb = $GLOBALS['wpdb'] ?? null;
	if ( ! $wpdb instanceof \wpdb ) {
		throw new \LogicException( 'The uninstall engine needs a wpdb connection.' );
	}

	return $wpdb;
}
