<?php declare( strict_types=1 );

namespace Acme\ConsumerA\Reports;

use Acme\ConsumerA\Options;
use Acme\ConsumerA\Scoped\DeepWebSolutions\Framework\Core\CompositeComponentInterface;
use Acme\ConsumerA\Scoped\DeepWebSolutions\Framework\Core\ConditionalComponentInterface;

\defined( 'ABSPATH' ) || exit;

/**
 * Groups the reports, which load only on sites that switch them on.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final class Reports implements CompositeComponentInterface, ConditionalComponentInterface {
	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	#[\Override]
	public static function should_load(): bool {
		return Options::get_bool( Options::REPORTS );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	#[\Override]
	public static function get_child_component_classes(): array {
		return array( ReportsPage::class );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	#[\Override]
	public function register_hooks(): void {}

	// endregion
}
