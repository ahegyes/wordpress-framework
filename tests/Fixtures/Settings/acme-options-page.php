<?php declare( strict_types=1 );
/**
 * Registers the request spec's options page with a plain closure on plugins_loaded.
 *
 * A field's validate and render may name a behavior RecordedDefinition::with_behaviors() lists, which records what it receives in the request's outcome. The outcome also lists the tabs whose fields the page asked for, and the _doing_it_wrong() calls in place of their notices. The spec may also contribute tabs through the page's tabs filter and fields through its fields filter, and update options programmatically on admin_init.
 *
 * @package Acme
 */

use DeepWebSolutions\Framework\Core\PluginIdentity;
use DeepWebSolutions\Framework\Settings\AdminPage\OptionsPage;
use DeepWebSolutions\Framework\Tests\Integration\Support\RecordedDefinition;

require_once dirname( __DIR__, 3 ) . '/vendor/autoload.php';

add_filter( 'doing_it_wrong_trigger_error', '__return_false' );
add_action(
	'doing_it_wrong_run',
	static function ( string $function_name, string $message ): void {
		$GLOBALS['acme_outcome']['doing_it_wrong'][] = array( $function_name, $message );
	},
	10,
	2
);

add_action(
	'plugins_loaded',
	static function () use ( $spec ): void {
		$page = new OptionsPage(
			new PluginIdentity( __FILE__, $spec['slug'], $spec['prefix'] ),
			$spec['id'],
			new RecordedDefinition(
				static function ( string $page_id, ?string $tab_slug = null ) use ( $spec ): array {
					if ( is_null( $tab_slug ) ) {
						return $spec['page'];
					}

					$GLOBALS['acme_outcome']['asked'][] = $tab_slug;

					return RecordedDefinition::with_behaviors( $spec['fields'][ $tab_slug ] ?? array() );
				}
			)
		);
		$page->register_hooks();
	}
);

$acme_hook_root = str_replace( '-', '_', $spec['slug'] );
if ( isset( $spec['contributed']['tabs'] ) ) {
	add_filter(
		"$acme_hook_root/options_page/tabs",
		static fn ( array $tabs, string $page_id ): array => $spec['id'] === $page_id ? array_merge( $tabs, $spec['contributed']['tabs'] ) : $tabs,
		10,
		2
	);
}
if ( isset( $spec['contributed']['fields'] ) ) {
	add_filter(
		"$acme_hook_root/options_page/fields",
		static fn ( array $fields, string $tab_slug, string $page_id ): array => $spec['id'] === $page_id ? array_merge( $fields, RecordedDefinition::with_behaviors( $spec['contributed']['fields'][ $tab_slug ] ?? array() ) ) : $fields,
		10,
		3
	);
}

if ( isset( $spec['update'] ) ) {
	add_action(
		'admin_init',
		static function () use ( $spec ): void {
			foreach ( $spec['update'] as $option => $value ) {
				update_option( $option, $value );
			}
		},
		20
	);
}
