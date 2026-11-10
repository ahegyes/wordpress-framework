<?php declare( strict_types=1 );
/**
 * Adds the request spec's settings tab on woocommerce_get_settings_pages and registers its settings section through WooCommerce's registry.
 *
 * A field's validate and render may name a behavior RecordedDefinition::with_behaviors() lists, which records what it receives in the request's outcome. The spec may also contribute fields to a section through WooCommerce's settings filter.
 *
 * @package Acme
 */

use DeepWebSolutions\Framework\Tests\Integration\Support\RecordedDefinition;
use DeepWebSolutions\Framework\WooCommerce\Setting\SettingsSection;
use DeepWebSolutions\Framework\WooCommerce\Setting\SettingsTab;

require_once dirname( __DIR__, 3 ) . '/vendor/autoload.php';

if ( isset( $spec['tab'] ) ) {
	add_filter(
		'woocommerce_get_settings_pages',
		static function ( array $pages ) use ( $spec ): array {
			$pages[] = new SettingsTab(
				$spec['tab']['id'],
				array(
					'label'    => $spec['tab']['label'],
					'sections' => array_map( static fn ( array $section ): string => $section['label'], $spec['tab']['sections'] ),
				),
				new RecordedDefinition( static fn ( string $tab_id, string $section_id ): array => RecordedDefinition::with_behaviors( $spec['tab']['sections'][ $section_id ]['fields'] ) )
			);

			return $pages;
		}
	);
}

if ( isset( $spec['section'] ) ) {
	add_action(
		'woocommerce_settings_sections_registration',
		static function ( $registry ) use ( $spec ): void {
			$registry->register(
				new SettingsSection(
					$spec['section']['id'],
					array(
						'tab'   => $spec['section']['tab'],
						'label' => $spec['section']['label'],
					),
					new RecordedDefinition( static fn (): array => RecordedDefinition::with_behaviors( $spec['section']['fields'] ) )
				)
			);
		}
	);
}

if ( isset( $spec['contributed'] ) ) {
	add_filter(
		"woocommerce_get_settings_{$spec['contributed']['tab']}",
		static fn ( array $settings, mixed $section_id ): array => $spec['contributed']['section'] === $section_id ? array_merge( $settings, RecordedDefinition::with_behaviors( $spec['contributed']['fields'] ) ) : $settings,
		10,
		2
	);
}
