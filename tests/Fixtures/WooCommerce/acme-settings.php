<?php declare( strict_types=1 );
/**
 * Adds the request spec's settings tab on woocommerce_get_settings_pages and registers its settings section through WooCommerce's registry, as a plugin without the kernel does.
 *
 * A field's validate may name a behavior as acme:append, acme:uppercase, acme:reject, acme:reject-silently or acme:record, and its render may be acme:render; each records what it receives in the request's outcome. The spec may also contribute fields to a section through WooCommerce's settings filter.
 *
 * @package Acme
 */

use DeepWebSolutions\Framework\Tests\Integration\Support\RecordedDefinition;
use DeepWebSolutions\Framework\WooCommerce\Setting\SettingsSection;
use DeepWebSolutions\Framework\WooCommerce\Setting\SettingsTab;

require_once dirname( __DIR__, 3 ) . '/vendor/autoload.php';

$acme_with_behaviors = static function ( array $fields ): array {
	foreach ( $fields as &$field ) {
		$field_id = $field['id'] ?? '';
		if ( 'acme:render' === ( $field['render'] ?? null ) ) {
			$field['render'] = static function ( array $field, mixed $value, string $name, string $id ) use ( $field_id ): string {
				$GLOBALS['acme_outcome']['records'][] = array( 'render', $field_id, $value, $name, $id );

				return '<input type="hidden" name="' . esc_attr( $name ) . '" value="" /><input type="text" class="acme-widget" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( is_string( $value ) ? $value : '' ) . '" />';
			};
		}

		$behavior = $field['validate'] ?? null;
		if ( is_string( $behavior ) && str_starts_with( $behavior, 'acme:' ) ) {
			$field['validate'] = static function ( mixed $raw ) use ( $behavior, $field_id ): mixed {
				$GLOBALS['acme_outcome']['records'][] = array( 'validate', $field_id, $raw );

				return match ( $behavior ) {
					'acme:append'          => $raw . '!',
					'acme:uppercase'       => strtoupper( $raw ),
					'acme:reject'          => new WP_Error( 'acme_rejected', 'Use at most four characters.' ),
					'acme:reject-silently' => new WP_Error( 'acme_rejected' ),
					'acme:record'          => $raw,
				};
			};
		}
	}

	return $fields;
};

if ( isset( $spec['tab'] ) ) {
	add_filter(
		'woocommerce_get_settings_pages',
		static function ( array $pages ) use ( $spec, $acme_with_behaviors ): array {
			$pages[] = new SettingsTab(
				$spec['tab']['id'],
				array(
					'label'    => $spec['tab']['label'],
					'sections' => array_map( static fn ( array $section ): string => $section['label'], $spec['tab']['sections'] ),
				),
				new RecordedDefinition( static fn ( string $tab_id, string $section_id ): array => $acme_with_behaviors( $spec['tab']['sections'][ $section_id ]['fields'] ) )
			);

			return $pages;
		}
	);
}

if ( isset( $spec['section'] ) ) {
	add_action(
		'woocommerce_settings_sections_registration',
		static function ( $registry ) use ( $spec, $acme_with_behaviors ): void {
			$registry->register(
				new SettingsSection(
					$spec['section']['id'],
					array(
						'tab'   => $spec['section']['tab'],
						'label' => $spec['section']['label'],
					),
					new RecordedDefinition( static fn (): array => $acme_with_behaviors( $spec['section']['fields'] ) )
				)
			);
		}
	);
}

if ( isset( $spec['contributed'] ) ) {
	add_filter(
		"woocommerce_get_settings_{$spec['contributed']['tab']}",
		static fn ( array $settings, mixed $section_id ): array => $spec['contributed']['section'] === $section_id ? array_merge( $settings, $acme_with_behaviors( $spec['contributed']['fields'] ) ) : $settings,
		10,
		2
	);
}
