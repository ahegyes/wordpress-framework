<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Shared\Version;

use DeepWebSolutions\Framework\Shared\Exception\InvalidArgumentException;
use DeepWebSolutions\Framework\Shared\ValueObject\AbstractValueObject;

/**
 * A version the plugin controls, ordered by SemVer precedence.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final readonly class Version extends AbstractValueObject implements \Stringable {
	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int         $major              The major version.
	 * @param   int         $minor              The minor version.
	 * @param   int         $patch              The patch version, 0 when omitted.
	 * @param   string|null $pre_release        The lowercase pre-release label, or null for a release.
	 * @param   int         $pre_release_number The pre-release number, 0 when omitted.
	 */
	protected function __construct(
		public int $major,
		public int $minor,
		public int $patch,
		public ?string $pre_release,
		public int $pre_release_number
	) {}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function __toString(): string {
		$label  = \is_null( $this->pre_release ) ? '' : "-$this->pre_release";
		$number = 0 === $this->pre_release_number ? '' : ".$this->pre_release_number";

		return "$this->major.$this->minor.$this->patch$label$number";
	}

	// endregion

	// region METHODS

	/**
	 * Returns -1, 0 or 1 when this version is lower than, equal to or higher than another.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   self $other The version to compare with.
	 *
	 * @return  int<-1, 1>
	 */
	public function compare( self $other ): int {
		return $this->get_precedence() <=> $other->get_precedence();
	}

	// endregion

	// region FACTORY METHODS

	/**
	 * Parses a MAJOR.MINOR[.PATCH][-LABEL[[.]NUMBER]] version whose label is dev, alpha, beta or rc in any case.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $version The version to parse.
	 *
	 * @throws  InvalidArgumentException Thrown when the version does not follow that form.
	 *
	 * @return  self
	 */
	public static function from_string( string $version ): self {
		if ( 1 !== \preg_match( '/\A(0|[1-9]\d*)\.(0|[1-9]\d*)(?:\.(0|[1-9]\d*))?(?:-(dev|alpha|beta|rc)(?:\.?(0|[1-9]\d*))?)?\z/i', $version, $parts, \PREG_UNMATCHED_AS_NULL ) ) {
			throw new InvalidArgumentException( "'$version' does not parse as a version." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing, never rendered.
		}

		return new self(
			(int) $parts[1],
			(int) $parts[2],
			(int) ( $parts[3] ?? 0 ),
			\is_null( $parts[4] ) ? null : \strtolower( $parts[4] ),
			(int) ( $parts[5] ?? 0 )
		);
	}

	// endregion

	// region HELPERS

	/**
	 * Returns the parts in precedence order, with the labels dev, alpha, beta and rc ranked below a release.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  array{int, int, int, int, int}
	 */
	protected function get_precedence(): array {
		$label_rank = match ( $this->pre_release ) {
			'dev'   => 0,
			'alpha' => 1,
			'beta'  => 2,
			'rc'    => 3,
			default => 4,
		};

		return array( $this->major, $this->minor, $this->patch, $label_rank, $this->pre_release_number );
	}

	// endregion
}
