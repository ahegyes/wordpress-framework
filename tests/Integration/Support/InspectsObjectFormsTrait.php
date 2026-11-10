<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Support;

// The using test isolates storage and sets $this->id to the form's id and $this->admin to the user its requests run as.
trait InspectsObjectFormsTrait {
	use RunsAdminRequestsTrait;
	use SerializesFormsTrait;

	protected const string FIXTURE = __DIR__ . '/../../Fixtures/Settings/acme-object-forms.php';

	protected const array COLORS = array(
		'red'  => 'Red',
		'blue' => 'Blue',
		'teal' => 'Teal',
	);

	protected function field( string $type, string $name, array $keys = array() ): array {
		return $keys + array(
			'type'  => $type,
			'id'    => "_{$this->prefix}_$name",
			'title' => \ucfirst( $name ),
		);
	}

	protected function control_name( string $name ): string {
		return "{$this->id}[_{$this->prefix}_$name]";
	}

	protected function follow( array $spec, array $saved ): array {
		self::assertIsString( $saved['location'] );

		return $this->admin_request( 'GET', \explode( '/wp-admin/', $saved['location'], 2 )[1], $this->admin, self::FIXTURE, $spec );
	}

	protected function records( array $request, string $kind ): array {
		return \array_values( \array_filter( $request['records'], static fn ( array $record ): bool => $kind === $record[0] ) );
	}

	protected static function rejections( string $html ): array {
		$messages = array();
		foreach ( self::document( $html )->querySelectorAll( 'div.notice-error.is-dismissible > p' ) as $message ) {
			$messages[] = \trim( $message->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		}

		return $messages;
	}

	protected function box( \Dom\HTMLDocument $screen ): \Dom\Element {
		$box = $screen->querySelector( "div.postbox[id=\"$this->id\"] div.inside" );
		self::assertNotNull( $box, "The screen printed no $this->id box." );

		return $box;
	}

	// The scope is the screen's element that holds the form, such as a meta box's body or the profile's form.
	protected function control( \Dom\ParentNode $scope, string $name ): \Dom\Element {
		$control = $scope->querySelector( "[id=\"$this->id._{$this->prefix}_$name\"]" );
		self::assertNotNull( $control, "The form printed no _{$this->prefix}_$name." );

		return $control;
	}

	protected function selected( \Dom\ParentNode $scope, string $name ): array {
		$values = array();
		foreach ( $this->control( $scope, $name )->querySelectorAll( 'option[selected]' ) as $option ) {
			$values[] = (string) $option->getAttribute( 'value' );
		}

		return $values;
	}
}
