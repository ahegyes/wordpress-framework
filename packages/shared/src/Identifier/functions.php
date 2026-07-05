<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Shared\Identifier;

/**
 * Whether a string is a valid identifier: a lowercase letter followed by lowercase letters, digits,
 * underscores, or hyphens. The strict charset for a caller-supplied name reused verbatim in a derived
 * context — a storage key, a form-field-name segment, a DOM id — validated at construction so a
 * malformed name fails at wiring time rather than at use.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string $identifier Identifier to validate.
 *
 * @return  bool
 */
function is_valid_identifier( string $identifier ): bool {
	return 1 === \preg_match( '/\A[a-z][a-z0-9_-]*\z/', $identifier );
}

/**
 * Whether a string is a valid global-name prefix: an optional leading underscore, then a lowercase
 * letter followed by lowercase letters, digits, underscores, or hyphens. The identifier charset plus
 * the leading-underscore convention for names in a shared global registry that hides an
 * underscore-prefixed entry from generic listings.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string $prefix Prefix to validate.
 *
 * @return  bool
 */
function is_valid_global_name_prefix( string $prefix ): bool {
	return 1 === \preg_match( '/\A_?[a-z][a-z0-9_-]*\z/', $prefix );
}
