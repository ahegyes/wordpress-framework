<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Hooks;

/**
 * Fires a current hook and a deprecated legacy alias in lock-step.
 *
 * Plugins evolving their public hook surface call {@see self::do_action_pair()} or
 * {@see self::apply_filters_pair()} instead of WordPress's do_action() / apply_filters()
 * directly. The current hook fires; the legacy hook fires through WordPress core's
 * do_action_deprecated() / apply_filters_deprecated() which also emits the
 * _deprecated_hook() notice when WP_DEBUG is on.
 *
 * Variadic by design — the call site mirrors a normal do_action() / apply_filters()
 * call, plus the deprecation metadata as leading arguments.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final readonly class DeprecatedHooksDispatcher {
	// region METHODS

	/**
	 * Fire a current action and its deprecated legacy alias.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   non-empty-string $current_hook    Current action hook name (the canonical one).
	 * @param   non-empty-string $deprecated_hook Legacy action hook name kept alive for backwards compatibility.
	 * @param   string           $deprecated_in   Plugin version in which the legacy hook was deprecated (e.g., '2.0.0').
	 * @param   mixed            ...$args         Positional arguments to pass to both hooks.
	 */
	public function do_action_pair( string $current_hook, string $deprecated_hook, string $deprecated_in, mixed ...$args ): void {
		\do_action( $current_hook, ...$args );
		\do_action_deprecated( $deprecated_hook, $args, $deprecated_in, $current_hook );
	}

	/**
	 * Apply a current filter and its deprecated legacy alias to a value, in order.
	 *
	 * The current hook runs first; the deprecated hook receives the result of the current
	 * hook. Consumers subscribed to the legacy hook see the value as already filtered by
	 * the current hook.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   non-empty-string $current_hook    Current filter hook name.
	 * @param   non-empty-string $deprecated_hook Legacy filter hook name kept alive for backwards compatibility.
	 * @param   string           $deprecated_in   Plugin version in which the legacy hook was deprecated.
	 * @param   mixed            $value           Value being filtered.
	 * @param   mixed            ...$extra_args   Additional positional arguments passed alongside $value.
	 *
	 * @return  mixed
	 */
	public function apply_filters_pair( string $current_hook, string $deprecated_hook, string $deprecated_in, mixed $value, mixed ...$extra_args ): mixed {
		$value = \apply_filters( $current_hook, $value, ...$extra_args );
		return \apply_filters_deprecated(
			$deprecated_hook,
			array_merge( array( $value ), $extra_args ),
			$deprecated_in,
			$current_hook,
		);
	}

	// endregion
}
