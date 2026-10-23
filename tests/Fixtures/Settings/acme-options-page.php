<?php declare( strict_types=1 );
/**
 * Registers the request spec's options page with a plain closure on plugins_loaded, as a plugin without the kernel does.
 *
 * A field's validate may name a behavior as acme:append, acme:reject, acme:reject-silently or acme:record; each records what it receives in the request's outcome. The spec may also contribute tabs and fields through the page's tabs filter, and update options programmatically on admin_init.
 *
 * @package Acme
 */

use DeepWebSolutions\Framework\Settings\AdminPage\OptionsPage;

require_once dirname( __DIR__, 3 ) . '/vendor/autoload.php';

$acme_with_behaviors = static function ( array $tabs ): array {
	foreach ( $tabs as &$tab ) {
		foreach ( $tab['fields'] as &$field ) {
			$behavior = $field['validate'] ?? null;
			if ( ! is_string( $behavior ) || ! str_starts_with( $behavior, 'acme:' ) ) {
				continue;
			}

			$field_id          = $field['id'];
			$field['validate'] = static function ( mixed $raw ) use ( $behavior, $field_id ): mixed {
				$GLOBALS['acme_outcome']['records'][] = array( $field_id, $raw );

				return match ( $behavior ) {
					'acme:append'          => $raw . '!',
					'acme:reject'          => new WP_Error( 'acme_rejected', 'Use at most four characters.' ),
					'acme:reject-silently' => new WP_Error( 'acme_rejected' ),
					'acme:record'          => $raw,
				};
			};
		}
	}

	return $tabs;
};

add_action(
	'plugins_loaded',
	static function () use ( $spec, $acme_with_behaviors ): void {
		$page = new OptionsPage(
			$spec['id'],
			static fn (): array => array( 'tabs' => $acme_with_behaviors( $spec['page']['tabs'] ) ) + $spec['page']
		);
		$page->register_hooks();
	}
);

if ( isset( $spec['contributed'] ) ) {
	add_filter(
		"{$spec['id']}_tabs",
		static function ( array $tabs ) use ( $spec, $acme_with_behaviors ): array {
			foreach ( $tabs as &$tab ) {
				$tab['fields'] = array_merge( $tab['fields'], $spec['contributed']['fields'][ $tab['slug'] ] ?? array() );
			}

			return $acme_with_behaviors( array_merge( $tabs, $spec['contributed']['tabs'] ?? array() ) );
		}
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
