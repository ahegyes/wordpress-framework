<?php declare( strict_types=1 );
/**
 * Registers the request spec's options page with a plain closure on plugins_loaded.
 *
 * A field's validate and render may name a behavior RecordedDefinition::with_behaviors() lists, which records what it receives in the request's outcome. The spec may also contribute tabs and fields through the page's tabs filter, and update options programmatically on admin_init.
 *
 * @package Acme
 */

use DeepWebSolutions\Framework\Core\PluginIdentity;
use DeepWebSolutions\Framework\Settings\AdminPage\OptionsPage;
use DeepWebSolutions\Framework\Tests\Integration\Support\RecordedDefinition;

require_once dirname( __DIR__, 3 ) . '/vendor/autoload.php';

$acme_with_behaviors = static function ( array $tabs ): array {
	foreach ( $tabs as &$tab ) {
		$tab['fields'] = RecordedDefinition::with_behaviors( $tab['fields'] );
	}

	return $tabs;
};

add_action(
	'plugins_loaded',
	static function () use ( $spec, $acme_with_behaviors ): void {
		$page = new OptionsPage(
			new PluginIdentity( __FILE__, $spec['slug'], 'acme' ),
			$spec['id'],
			new RecordedDefinition( static fn (): array => array( 'tabs' => $acme_with_behaviors( $spec['page']['tabs'] ) ) + $spec['page'] )
		);
		$page->register_hooks();
	}
);

if ( isset( $spec['contributed'] ) ) {
	add_filter(
		str_replace( '-', '_', $spec['slug'] ) . '/options_page/tabs',
		static function ( array $tabs, string $page_id ) use ( $spec, $acme_with_behaviors ): array {
			if ( $spec['id'] !== $page_id ) {
				return $tabs;
			}

			foreach ( $tabs as &$tab ) {
				$tab['fields'] = array_merge( $tab['fields'], $spec['contributed']['fields'][ $tab['slug'] ] ?? array() );
			}

			return $acme_with_behaviors( array_merge( $tabs, $spec['contributed']['tabs'] ?? array() ) );
		},
		10,
		2
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
