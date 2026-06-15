<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings;

use DeepWebSolutions\Framework\Settings\Exceptions\DuplicateSettingsFieldException;
use DeepWebSolutions\Framework\Settings\ValueObjects\SettingsField;

/**
 * Merges field providers into one ordered, duplicate-free field list.
 *
 * Pure and WordPress-free. Concatenates each provider's fields in provider order,
 * rejects a repeated field id (field ids are page-unique), then stable-sorts by
 * position: fields with an explicit position sort ascending; unpositioned fields
 * sort last; ties — including all-unpositioned — keep insertion order.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class SettingsFieldAggregator {
	// region METHODS

	/**
	 * Aggregates the fields contributed by the given providers.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   list<SettingsFieldProviderInterface> $providers Providers whose fields are merged, in provider order.
	 *
	 * @throws  DuplicateSettingsFieldException If two contributed fields share an id.
	 *
	 * @return  list<SettingsField>
	 */
	public function aggregate( array $providers ): array {
		$fields = array();

		foreach ( $providers as $provider ) {
			foreach ( $provider->get_fields() as $field ) {
				if ( \array_key_exists( $field->id, $fields ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- framework-internal exception; never reaches an HTML output context unescaped.
					throw new DuplicateSettingsFieldException( "Duplicate settings field id: '$field->id'" );
				}
				$fields[ $field->id ] = $field;
			}
		}

		// usort reindexes to a list and is stable, so equal positions keep insertion order.
		\usort(
			$fields,
			static fn ( SettingsField $a, SettingsField $b ): int => ( $a->position ?? PHP_INT_MAX ) <=> ( $b->position ?? PHP_INT_MAX ),
		);

		return $fields;
	}

	// endregion
}
