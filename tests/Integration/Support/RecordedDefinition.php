<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Support;

use DeepWebSolutions\Framework\Settings\AdminPage\OptionsPageDefinitionInterface;
use DeepWebSolutions\Framework\Settings\EditScreen\ObjectFormDefinitionInterface;
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

	protected function record( mixed ...$arguments ): mixed {
		$this->calls[] = $arguments;

		return ( $this->define )( ...$arguments );
	}
}
