<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Unit\Logging;

use DeepWebSolutions\Framework\Utilities\Logging\CompositeLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

#[CoversClass( CompositeLogger::class )]
final class CompositeLoggerTest extends TestCase {
	public function test_delegates_records_to_every_inner_logger(): void {
		$first  = new CompositeRecordingLogger();
		$second = new CompositeRecordingLogger();
		$logger = new CompositeLogger( $first, $second );

		$logger->log( LogLevel::ERROR, 'Install failed', array( 'step' => 'schema' ) );

		self::assertSame( LogLevel::ERROR, $first->records[0]['level'] );
		self::assertSame( 'Install failed', $first->records[0]['message'] );
		self::assertSame( array( 'step' => 'schema' ), $first->records[0]['context'] );
		self::assertSame( $first->records, $second->records );
	}

	public function test_empty_composite_drops_records(): void {
		$this->expectNotToPerformAssertions();

		$logger = new CompositeLogger();

		$logger->error( 'nothing receives this' );
	}

	public function test_a_throwing_logger_does_not_starve_later_loggers_and_the_first_failure_propagates(): void {
		$first_failure  = new \RuntimeException( 'first sink failed' );
		$second_failure = new \RuntimeException( 'third sink failed' );
		$recorder       = new CompositeRecordingLogger();
		$logger         = new CompositeLogger(
			new CompositeThrowingLogger( $first_failure ),
			$recorder,
			new CompositeThrowingLogger( $second_failure ),
		);

		$caught = null;
		try {
			$logger->log( LogLevel::ERROR, 'Install failed', array( 'step' => 'schema' ) );
		} catch ( \Throwable $caught ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- asserted below; delivery must precede propagation.
			// Asserted below; the record must have been delivered before propagation.
		}

		self::assertSame( $first_failure, $caught );
		self::assertSame( LogLevel::ERROR, $recorder->records[0]['level'] );
		self::assertSame( 'Install failed', $recorder->records[0]['message'] );
		self::assertSame( array( 'step' => 'schema' ), $recorder->records[0]['context'] );
	}
}

final class CompositeThrowingLogger extends AbstractLogger {
	public function __construct(
		private readonly \Throwable $failure,
	) {}

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed                   $level   Log level.
	 * @param string|\Stringable      $message Log message.
	 * @param array<array-key, mixed> $context Log context.
	 */
	#[\Override]
	public function log( $level, string|\Stringable $message, array $context = array() ): void {
		throw $this->failure;
	}
}

final class CompositeRecordingLogger extends AbstractLogger {
	/**
	 * Logged records.
	 *
	 * @var list<array{level: mixed, message: string|\Stringable, context: array<array-key, mixed>}>
	 */
	public array $records = array();

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed                   $level   Log level.
	 * @param string|\Stringable      $message Log message.
	 * @param array<array-key, mixed> $context Log context.
	 */
	#[\Override]
	public function log( $level, string|\Stringable $message, array $context = array() ): void {
		$this->records[] = array(
			'level'   => $level,
			'message' => $message,
			'context' => $context,
		);
	}
}
