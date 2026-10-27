<?php declare( strict_types=1 );
/**
 * Runs copies of the consumer fixtures in the request: activates them on the site, defines the spec's constants and locale, skips WordPress's update check, and records how each consumer booted, once the admin initializes and again after a plugin's activation, and what consumer-a did.
 *
 * The spec may also mint the nonce of an action the request checks, relax consumer-a's export safeguard with a value, expect warnings for files a partial deploy removed, uninstall a copy, or run consumer-a's order-count export with a failing order query.
 *
 * @package Acme
 */

$acme_capability = 'acme_ca_export_orders';
$acme_missing    = $spec['missing'] ?? array();
$acme_previous   = null;
$acme_previous   = set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- PHP warns before it throws for a removed file.
	static function ( int $level, string $message, string $file, int $line ) use ( $acme_missing, &$acme_previous ): bool {
		foreach ( $acme_missing as $path ) {
			if ( str_contains( $message, $path ) ) {
				$GLOBALS['acme_outcome']['records']['warnings'][] = $message;
				return true;
			}
		}

		return is_callable( $acme_previous ) && (bool) $acme_previous( $level, $message, $file, $line );
	}
);

if ( isset( $spec['log'] ) ) {
	ini_set( 'error_log', $spec['log'] ); // phpcs:ignore WordPress.PHP.IniSet.Risky -- Captures the plugin's log lines.
}

foreach ( $spec['constants'] ?? array() as $acme_name => $acme_value ) {
	define( $acme_name, $acme_value ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.VariableConstantNameFound -- Defines what the spec names.
}

if ( isset( $spec['activate'] ) ) {
	add_filter( 'option_active_plugins', static fn ( mixed $plugins ): array => array_merge( is_array( $plugins ) ? $plugins : array(), array( $spec['activate'] ) ) );
}

if ( isset( $spec['locale'] ) ) {
	add_filter( 'pre_determine_locale', static fn (): string => $spec['locale'] );
}

if ( array_key_exists( 'relaxation', $spec ) ) {
	add_filter( 'acme_ca_restrict_exports', static fn (): mixed => $spec['relaxation'] );
}

$acme_record_boots = static function (): void {
	$consumers = array(
		'consumer_a' => 'acme_ca',
		'consumer_b' => 'acme_cb',
	);
	foreach ( $consumers as $key => $prefix ) {
		try {
			$plugin = function_exists( "{$prefix}_plugin" ) ? ( "{$prefix}_plugin" )() : null;
		} catch ( Error ) { // The composition root's class file may be missing.
			$plugin = null;
		}
		$GLOBALS['acme_outcome']['records'][ $key ] = array(
			'status'           => $plugin?->boot->status,
			'resolved'         => $plugin?->kernel?->resolved,
			'skipped'          => $plugin?->kernel?->skipped,
			'initialized'      => did_action( "{$prefix}_initialized" ),
			'functions_loaded' => function_exists( "{$prefix}_plugin" ),
		);
	}
};
add_action( 'activated_plugin', $acme_record_boots );

// A fresh update check keeps WordPress, and the plugin's updater with it, from fetching releases during the request.
add_filter( 'pre_site_transient_update_plugins', static fn (): object => (object) array( 'last_checked' => time() ) );

add_action(
	'plugins_loaded',
	static function () use ( $acme_capability ): void {
		$GLOBALS['acme_outcome']['records']['can_export_before_boot'] = current_user_can( $acme_capability );
	},
	14
);

add_action(
	'admin_init',
	static function () use ( $spec, $acme_capability, $acme_record_boots ): void {
		$records = &$GLOBALS['acme_outcome']['records'];
		if ( isset( $spec['uninstall'] ) ) {
			$records['deleted'] = delete_plugins( array( $spec['uninstall'] ) );
			exit; // WordPress redirects or answers as JSON after deleting a plugin, so no admin notice renders in the request.
		}

		if ( isset( $spec['nonce'] ) ) {
			$_REQUEST['_wpnonce'] = wp_create_nonce( $spec['nonce'] );
		}

		$acme_record_boots();
		$records += array(
			'woocommerce_present' => class_exists( 'WooCommerce', false ),
			'can_export'          => current_user_can( $acme_capability ),
			'safeguard_attached'  => has_filter( 'map_meta_cap', 'acme_ca_map_export_capability' ),
			'export_hooked'       => has_action( 'admin_post_acme_ca_export' ),
			'reports_page_class'  => class_exists( 'Acme\ConsumerA\Reports\ReportsPage', false ),
			'updater_hooked'      => has_filter( 'update_plugins_github.com' ),
			'schedules_hooked'    => has_filter( 'cron_schedules' ),
			'safeguards_loaded'   => function_exists( 'acme_ca_is_relaxed' ),
		);

		if ( $spec['export_throws'] ?? false ) {
			add_filter(
				'woocommerce_order_query_args',
				static function (): never {
					throw new RuntimeException( 'The order query failed.' );
				}
			);
			$_REQUEST['_wpnonce'] = wp_create_nonce( 'acme_ca_export' );
			try {
				do_action( 'admin_post_acme_ca_export' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Runs consumer-a's own action.
			} catch ( RuntimeException $exception ) {
				$records['export_error'] = $exception->getMessage();
			}
			$records['can_export_after_throw']         = current_user_can( $acme_capability );
			$records['relaxation_left']                = has_filter( 'acme_ca_restrict_exports', '__return_false' );
			$records['safeguard_attached_after_throw'] = has_filter( 'map_meta_cap', 'acme_ca_map_export_capability' );
		}
	},
	PHP_INT_MAX
);
