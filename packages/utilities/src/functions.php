<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities;

/**
 * Whether a string is a valid WordPress-global name prefix: an optional leading underscore,
 * then a lowercase letter followed by lowercase letters, digits, underscores, or hyphens.
 * Constructor-supplied strings reused as option keys, cache groups, or hook-name segments
 * gate on this so a malformed name fails at wiring time rather than at use.
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
