<?php declare( strict_types=1 );
/**
 * Prints, as JSON on its last line, what an administrator sees on the PHP 7.4 site's plugins screen: its PHP and WordPress versions, whether wp_admin_notice() exists, whether an updater is hooked, the capabilities consumer-a's order export maps to, and the admin notices.
 *
 * WP-CLI requires it with WordPress and the active plugins loaded, as wp-admin has them before its notices render. It parses on PHP 7.4.
 *
 * @package Acme
 */

wp_set_current_user( 1 );
set_current_screen( 'plugins' );

ob_start();
do_action( 'admin_notices' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Fires WordPress's own action.
do_action( 'all_admin_notices' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Fires WordPress's own action.
$acme_notices = (string) ob_get_clean();

echo "\n" . wp_json_encode( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prints JSON for the test, never a page.
	array(
		'php'             => PHP_VERSION,
		'wordpress'       => get_bloginfo( 'version' ),
		'wp_admin_notice' => function_exists( 'wp_admin_notice' ),
		'updater_hooked'  => false !== has_filter( 'update_plugins_github.com' ),
		'export_caps'     => map_meta_cap( 'acme_ca_export_orders', get_current_user_id() ),
		'notices'         => $acme_notices,
	)
);
