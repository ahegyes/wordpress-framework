<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Support;

trait SerializesFormsTrait {
	protected static function document( string $html ): \Dom\HTMLDocument {
		return \Dom\HTMLDocument::createFromString( $html, \LIBXML_NOERROR );
	}

	// Serializes the form as a browser submits it once the user has set the named visible controls: enabled controls in document order, checked boxes and selected options only.
	protected static function serialize_form( string $html, string $form_selector, array $changes = array() ): array {
		$document = self::document( $html );
		self::assertNotNull( $document->querySelector( $form_selector ), "The page printed no $form_selector." );

		$pairs = array();
		foreach ( $document->querySelectorAll( "$form_selector [name]" ) as $control ) {
			$name    = (string) $control->getAttribute( 'name' );
			$type    = (string) $control->getAttribute( 'type' );
			$base    = \str_ends_with( $name, '[]' ) ? \substr( $name, 0, -2 ) : $name;
			$changed = 'hidden' !== $type && \array_key_exists( $base, $changes );
			$change  = $changed ? $changes[ $base ] : null;
			if ( $control->hasAttribute( 'disabled' ) || 'submit' === $type ) {
				continue;
			}

			if ( 'textarea' === $control->localName ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
				$pairs[] = array( $name, $changed ? $change : $control->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
			} elseif ( 'select' === $control->localName ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
				$selected = array();
				foreach ( $control->querySelectorAll( 'option' ) as $option ) {
					$value = (string) $option->getAttribute( 'value' );
					if ( $changed ? \in_array( $value, (array) $change, true ) : $option->hasAttribute( 'selected' ) ) {
						$selected[] = $value;
					}
				}
				if ( array() === $selected && ! $control->hasAttribute( 'multiple' ) ) {
					$selected[] = (string) $control->querySelector( 'option' )?->getAttribute( 'value' );
				}
				foreach ( $selected as $value ) {
					$pairs[] = array( $name, $value );
				}
			} elseif ( 'checkbox' === $type || 'radio' === $type ) {
				$value = (string) $control->getAttribute( 'value' );
				if ( $changed ? ( \is_bool( $change ) ? $change : \in_array( $value, (array) $change, true ) ) : $control->hasAttribute( 'checked' ) ) {
					$pairs[] = array( $name, $value );
				}
			} else {
				$pairs[] = array( $name, $changed ? $change : (string) $control->getAttribute( 'value' ) );
			}
		}

		\parse_str( \implode( '&', \array_map( static fn ( array $pair ): string => \rawurlencode( $pair[0] ) . '=' . \rawurlencode( $pair[1] ), $pairs ) ), $data );

		return $data;
	}
}
