<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Fixtures\Core;

use DeepWebSolutions\Framework\CompositeComponentInterface;
use DeepWebSolutions\Framework\ConditionalComponentInterface;

final class GatedOutComposite implements CompositeComponentInterface, ConditionalComponentInterface {
	#[\Override]
	public static function should_load(): bool {
		return false;
	}

	#[\Override]
	public static function get_child_component_classes(): array {
		return array( UnloadableComponent::class );
	}

	#[\Override]
	public function register_hooks(): void {}
}
