<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Shared\Version;

use DeepWebSolutions\Framework\Shared\ValueObject\AbstractValueObject;
use DeepWebSolutions\Framework\Shared\Version\Exceptions\InvalidVersionException;

/**
 * Value object representing a software version. SemVer-shaped:
 * MAJOR[.MINOR[.PATCH]][-PRERELEASE][+BUILD]. Provides type-safe ordering and
 * equality.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final readonly class Version extends AbstractValueObject {
	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $value Pre-validated version string; the original representation supplied to the factory.
	 */
	protected function __construct(
		public string $value
	) {}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function __toString(): string {
		return $this->value;
	}

	// endregion

	// region METHODS

	/**
	 * Whether this version is strictly greater than `$other`.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   self $other Version to compare against.
	 *
	 * @return  bool
	 */
	public function is_greater_than( self $other ): bool {
		return \version_compare( $this->value, $other->value, '>' );
	}

	/**
	 * Whether this version is strictly less than `$other`.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   self $other Version to compare against.
	 *
	 * @return  bool
	 */
	public function is_less_than( self $other ): bool {
		return \version_compare( $this->value, $other->value, '<' );
	}

	/**
	 * Whether this version is equal to `$other` per PHP `version_compare()`
	 * semantics: numerically equivalent segments match (`1.0` equals `1.00`,
	 * `RC1` equals `rc1`) but omitted components do not (`2.0` is less than
	 * `2.0.0`), and build metadata participates in the comparison — unlike
	 * SemVer, which ignores it. Structural string-identity equality is the
	 * inherited {@see AbstractValueObject::equals()}.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   self $other Version to compare against.
	 *
	 * @return  bool
	 */
	public function is_equal_to( self $other ): bool {
		return 0 === \version_compare( $this->value, $other->value );
	}

	// endregion

	// region FACTORY METHODS

	/**
	 * Constructs a Version from a string. Validates against SemVer-ish shape.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $value Version string (e.g., `2.0.0`, `2.0`, `2.0.0-beta.1`).
	 *
	 * @throws  InvalidVersionException If `$value` does not parse as a version.
	 *
	 * @return  self
	 */
	public static function from_string( string $value ): self {
		$pattern = '/^\d+(\.\d+){0,2}(-[0-9A-Za-z-]+(\.[0-9A-Za-z-]+)*)?(\+[0-9A-Za-z-]+(\.[0-9A-Za-z-]+)*)?$/';
		if ( 1 !== \preg_match( $pattern, $value ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- framework-internal exception; never reaches an HTML output context unescaped.
			throw new InvalidVersionException( "'$value' does not parse as a version" );
		}

		return new self( $value );
	}

	/**
	 * Constructs a Version from individual parts. Omitted components are
	 * omitted from the resulting version string too — `from_parts( 2, 0 )`
	 * yields `2.0`, which compares differently to `2.0.0` under
	 * `version_compare()` semantics.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int         $major      Major version component.
	 * @param   int|null    $minor      Minor version component.
	 * @param   int|null    $patch      Patch version component; requires `$minor`.
	 * @param   string|null $prerelease Pre-release identifiers (e.g., `beta.1`).
	 * @param   string|null $build      Build metadata identifiers (e.g., `build.5`).
	 *
	 * @throws  InvalidVersionException If the parts do not compose a valid version.
	 *
	 * @return  self
	 */
	public static function from_parts( int $major, ?int $minor = null, ?int $patch = null, ?string $prerelease = null, ?string $build = null ): self {
		if ( null === $minor && null !== $patch ) {
			throw new InvalidVersionException( 'a patch version requires a minor version' );
		}

		$value  = (string) $major;
		$value .= null !== $minor ? ".$minor" : '';
		$value .= null !== $patch ? ".$patch" : '';
		$value .= null !== $prerelease ? "-$prerelease" : '';
		$value .= null !== $build ? "+$build" : '';

		return self::from_string( $value );
	}

	// endregion
}
