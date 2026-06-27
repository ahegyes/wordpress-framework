<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\MetaField\ValueObjects;

use DeepWebSolutions\Framework\Settings\MetaField\Exceptions\InvalidTermFieldGroupException;

/**
 * Descriptor for a group of fields on a taxonomy term-edit surface.
 *
 * Wraps a {@see FieldGroup} with the taxonomy whose term-edit screen the fields attach to. The taxonomy
 * is interpolated into the term hooks, so it must match WordPress's taxonomy-key rules.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final readonly class TermFieldGroup {
	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   FieldGroup $group Surface-agnostic group of fields to render on the term-edit screen.
	 * @param   string     $taxonomy Taxonomy whose term-edit screen the fields attach to; a 1–32 character key of lowercase letters, digits, underscores, or hyphens.
	 *
	 * @throws  InvalidTermFieldGroupException If $taxonomy is not a valid WordPress taxonomy key.
	 */
	public function __construct(
		public FieldGroup $group,
		public string $taxonomy,
	) {
		// WordPress taxonomy keys are 1–32 characters of lowercase letters, digits, underscores, or hyphens
		// (no leading-letter requirement), distinct from the settings id charset.
		if ( 1 !== \preg_match( '/\A[a-z0-9_-]{1,32}\z/', $taxonomy ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- framework-internal exception; never reaches an HTML output context unescaped.
			throw new InvalidTermFieldGroupException( "Invalid taxonomy for term field group: '$taxonomy'" );
		}
	}

	// endregion
}
