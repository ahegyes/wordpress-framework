<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Schema\Options;

use DeepWebSolutions\Framework\Settings\Schema\Exceptions\InvalidSettingsOptionsException;

/**
 * Resolves a field's options source into a concrete value-to-label map.
 *
 * Pure and WordPress-free. Accepts the three option-source forms — a literal
 * array, a closure, or a provider — and returns the same map for equivalent
 * sources, so rendering and validation always agree on the option set. A closure
 * that yields a non-array is a misuse and throws.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final readonly class OptionsResolver {
	// region METHODS

	/**
	 * Resolves an options source to a value-to-label map.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<array-key, mixed>|\Closure|OptionsProviderInterface $options Options source to resolve.
	 *
	 * @throws  InvalidSettingsOptionsException If a closure source resolves to a non-array.
	 *
	 * @return  array<array-key, mixed>
	 */
	public function resolve( array|\Closure|OptionsProviderInterface $options ): array {
		if ( $options instanceof OptionsProviderInterface ) {
			return $options->get_options();
		}

		if ( $options instanceof \Closure ) {
			$resolved = ( $options )();
			if ( ! \is_array( $resolved ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- framework-internal exception; never reaches an HTML output context unescaped.
				throw new InvalidSettingsOptionsException( 'A settings field options closure must return an array.' );
			}
			return $resolved;
		}

		return $options;
	}

	// endregion
}
