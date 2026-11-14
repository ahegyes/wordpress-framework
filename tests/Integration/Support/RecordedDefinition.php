<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Support;

use DeepWebSolutions\Framework\Settings\AdminPage\OptionsPageDefinitionInterface;
use DeepWebSolutions\Framework\Settings\EditScreen\ObjectFormDefinitionInterface;
use DeepWebSolutions\Framework\Settings\Forms;
use DeepWebSolutions\Framework\WooCommerce\ProductData\ProductDataTabDefinitionInterface;
use DeepWebSolutions\Framework\WooCommerce\Setting\SectionFieldsDefinitionInterface;

final class RecordedDefinition implements OptionsPageDefinitionInterface, ObjectFormDefinitionInterface, SectionFieldsDefinitionInterface, ProductDataTabDefinitionInterface {
	public array $calls = array();

	public function __construct(
		protected \Closure $define
	) {}

	#[\Override]
	public function get_options_page( string $page_id ): array {
		return $this->record( $page_id );
	}

	#[\Override]
	public function get_tab_fields( string $page_id, string $tab_slug ): array {
		return $this->record( $page_id, $tab_slug );
	}

	#[\Override]
	public function get_object_form( string $form_id, int $object_id ): array {
		return $this->record( $form_id, $object_id );
	}

	#[\Override]
	public function get_section_fields( string $tab_id, string $section_id ): array {
		return $this->record( $tab_id, $section_id );
	}

	#[\Override]
	public function get_product_data_tab( string $tab_id, \WC_Product $product ): ?array {
		return $this->record( $tab_id, $product );
	}

	// Replaces each field's acme: validate and render names with callables that record what they receive in the request's outcome.
	public static function with_behaviors( array $fields ): array {
		foreach ( $fields as &$field ) {
			$field_id = $field['id'] ?? '';
			if ( 'acme:render' === ( $field['render'] ?? null ) ) {
				$field['render'] = static function ( array $field, mixed $value, string $name, string $id ) use ( $field_id ): string {
					$GLOBALS['acme_outcome']['records'][] = array( 'render', $field_id, $value, $name, $id );

					return '<input type="hidden" name="' . esc_attr( $name ) . '" value="" /><input type="text" class="acme-widget" id="' . Forms::escape_attribute( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( \is_string( $value ) ? $value : '' ) . '" />';
				};
			}

			$behavior = $field['validate'] ?? null;
			if ( \is_string( $behavior ) && \str_starts_with( $behavior, 'acme:' ) ) {
				$field['validate'] = static function ( mixed $raw ) use ( $behavior, $field_id ): mixed {
					$GLOBALS['acme_outcome']['records'][] = array( 'validate', $field_id, $raw );

					return match ( $behavior ) {
						'acme:append'          => $raw . '!',
						'acme:uppercase'       => \strtoupper( $raw ),
						'acme:reject'          => new \WP_Error( 'acme_rejected', 'Use at most four characters.' ),
						'acme:reject-silently' => new \WP_Error( 'acme_rejected' ),
						'acme:record'          => $raw,
					};
				};
			}
		}

		return $fields;
	}

	protected function record( mixed ...$arguments ): mixed {
		$this->calls[] = $arguments;

		return ( $this->define )( ...$arguments );
	}
}
