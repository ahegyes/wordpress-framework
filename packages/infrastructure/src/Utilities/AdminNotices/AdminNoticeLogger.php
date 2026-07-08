<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\AdminNotices;

use DeepWebSolutions\Framework\Utilities\AdminNotices\Exceptions\InvalidNoticeIdentifierException;
use DeepWebSolutions\Framework\Utilities\AdminNotices\Exceptions\UnknownNoticeStoreException;
use DeepWebSolutions\Framework\Utilities\AdminNotices\ValueObjects\AdminNotice;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;

/**
 * PSR-3 logger that surfaces records at or above a threshold as a persistent admin notice.
 *
 * Each admitted record is queued through an {@see AdminNoticesService} under one stable notice ID, so
 * repeated records collapse onto a single bounded notice carrying the most recent message. Wiring an
 * instance as the kernel's optional logger turns a failed install or migration into admin-visible output;
 * pair it with a persistent store to surface the notice on a later request.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final readonly class AdminNoticeLogger implements LoggerInterface {
	// region TRAITS

	use LoggerTrait;

	// endregion

	// region FIELDS AND CONSTANTS

	/**
	 * RFC 5424 severity rank per PSR-3 level, ascending, used to weigh a record against the threshold.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     array<string, int>
	 */
	protected const SEVERITIES = array(
		LogLevel::DEBUG     => 0,
		LogLevel::INFO      => 1,
		LogLevel::NOTICE    => 2,
		LogLevel::WARNING   => 3,
		LogLevel::ERROR     => 4,
		LogLevel::CRITICAL  => 5,
		LogLevel::ALERT     => 6,
		LogLevel::EMERGENCY => 7,
	);

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   AdminNoticesService $service       Service the notice is queued through.
	 * @param   string              $notice_id     Stable ID every admitted record is queued under; must be sanitize_key-stable so AJAX dismissal round-trips.
	 * @param   string              $store         Name of the service store to queue in; a persistent store surfaces the notice on a later request.
	 * @param   string              $minimum_level Lowest PSR-3 level that produces a notice; records below it are dropped.
	 * @param   string              $capability    Capability required to see the notice.
	 * @param   bool                $dismissible   Whether the notice shows a dismiss button.
	 *
	 * @throws  InvalidNoticeIdentifierException When $notice_id is not sanitize_key-stable.
	 * @throws  InvalidArgumentException         When $minimum_level is not a PSR-3 level.
	 * @throws  UnknownNoticeStoreException      When $store is not registered on the service.
	 */
	public function __construct(
		protected AdminNoticesService $service,
		protected string $notice_id,
		protected string $store = AdminNoticesService::DEFAULT_STORE,
		protected string $minimum_level = LogLevel::ERROR,
		protected string $capability = 'manage_options',
		protected bool $dismissible = false,
	) {
		if ( ! namespace\is_valid_notice_id( $this->notice_id ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- framework-internal exception; never reaches an HTML output context unescaped.
			throw new InvalidNoticeIdentifierException( "Invalid notice id: '$this->notice_id'. Use a sanitize_key-stable id (lowercase a-z, 0-9, _, -) so AJAX dismissal round-trips." );
		}

		// The minimum level is PSR-3 vocabulary, so its rejection stays the PSR-3 exception type,
		// matching the level validation log() itself performs.
		if ( ! isset( self::SEVERITIES[ $this->minimum_level ] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- framework-internal exception; never reaches an HTML output context unescaped.
			throw new InvalidArgumentException( "Unknown minimum log level: '$this->minimum_level'." );
		}

		// Validate the target store up front: a misnamed store would otherwise surface only when the
		// first record is queued — inside the fail-closed kernel boot this logger exists to report.
		if ( ! isset( $this->service->stores[ $this->store ] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- framework-internal exception; never reaches an HTML output context unescaped.
			throw new UnknownNoticeStoreException( "No notice store is registered under name '$this->store'." );
		}
	}

	// endregion

	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @throws  InvalidArgumentException When $level is not a PSR-3 level.
	 */
	#[\Override]
	public function log( $level, string|\Stringable $message, array $context = array() ): void {
		$level_key = \is_string( $level ) ? $level : '';
		if ( ! isset( self::SEVERITIES[ $level_key ] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- framework-internal exception; never reaches an HTML output context unescaped.
			throw new InvalidArgumentException( \sprintf( "Unknown log level: '%s'.", \is_scalar( $level ) ? (string) $level : \gettype( $level ) ) );
		}

		if ( self::SEVERITIES[ $level_key ] < self::SEVERITIES[ $this->minimum_level ] ) {
			return;
		}

		$this->service->add_notice(
			new AdminNotice(
				id: $this->notice_id,
				message: $this->interpolate( (string) $message, $context ),
				type: $this->notice_type( $level_key ),
				dismissible: $this->dismissible,
				persistent: true,
				capability: $this->capability,
			),
			$this->store,
		);
	}

	// endregion

	// region HELPERS

	/**
	 * Maps a PSR-3 level to a notice severity: error and above are errors, warning is a warning, the rest informational.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $level PSR-3 level.
	 *
	 * @return  NoticeType
	 */
	protected function notice_type( string $level ): NoticeType {
		return match ( $level ) {
			LogLevel::EMERGENCY, LogLevel::ALERT, LogLevel::CRITICAL, LogLevel::ERROR => NoticeType::Error,
			LogLevel::WARNING => NoticeType::Warning,
			default           => NoticeType::Info,
		};
	}

	/**
	 * Substitutes the message's {placeholder} tokens with their scalar or stringable context values, per
	 * PSR-3 interpolation; a value whose token is absent is never stringified, so an unused costly-or-throwing
	 * one is left alone. Resolved values reach the admin-visible notice, so keep secrets and file paths out
	 * of placeholders.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string                  $message Message that may contain {placeholder} tokens.
	 * @param   array<array-key, mixed> $context Context values keyed by placeholder name.
	 *
	 * @return  string
	 */
	protected function interpolate( string $message, array $context ): string {
		$replacements = array();
		foreach ( $context as $key => $value ) {
			$token = '{' . $key . '}';
			if ( ! \str_contains( $message, $token ) ) {
				continue;
			}

			$stringified = $this->stringify( $value );
			if ( null !== $stringified ) {
				$replacements[ $token ] = $stringified;
			}
		}

		return \strtr( $message, $replacements );
	}

	/**
	 * Renders a context value for interpolation, or null when it cannot stand in for a placeholder: a
	 * non-scalar non-stringable, or a stringable whose __toString() throws — which must not break logging,
	 * since the logger runs inside the kernel's fail-closed boot.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed $value Context value.
	 *
	 * @return  string|null
	 */
	protected function stringify( mixed $value ): ?string {
		if ( ! \is_scalar( $value ) && ! $value instanceof \Stringable ) {
			return null;
		}

		try {
			return (string) $value;
		} catch ( \Throwable ) {
			return null;
		}
	}

	// endregion
}
