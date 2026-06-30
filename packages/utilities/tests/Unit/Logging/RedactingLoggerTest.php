<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Unit\Logging;

use DeepWebSolutions\Framework\Utilities\Logging\RedactingLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

#[CoversClass( RedactingLogger::class )]
final class RedactingLoggerTest extends TestCase {
	public function test_redaction_removes_sensitive_spans_from_the_message(): void {
		$inner  = new RecordingLogger();
		$logger = new RedactingLogger( $inner );

		$logger->error( 'Install failed for <sensitive>license-key</sensitive>.' );

		self::assertSame( 'Install failed for .', $inner->records[0]['message'] );
	}

	public function test_redaction_can_be_disabled(): void {
		$inner  = new RecordingLogger();
		$logger = new RedactingLogger( $inner, false );

		$logger->error( 'Install failed for <sensitive>license-key</sensitive>.' );

		self::assertSame( 'Install failed for <sensitive>license-key</sensitive>.', $inner->records[0]['message'] );
	}

	public function test_redaction_removes_sensitive_spans_from_context_strings(): void {
		$inner  = new RecordingLogger();
		$logger = new RedactingLogger( $inner );

		$logger->error(
			'Install failed.',
			array(
				'license' => '<sensitive>license-key</sensitive>',
				'nested'  => array( 'token' => 'Bearer <sensitive>secret</sensitive>' ),
			),
		);

		self::assertSame( '', $inner->records[0]['context']['license'] );
		self::assertSame( 'Bearer ', $inner->records[0]['context']['nested']['token'] );
	}

	public function test_redaction_passes_non_string_context_values_through_unchanged(): void {
		$inner     = new RecordingLogger();
		$logger    = new RedactingLogger( $inner );
		$throwable = new \RuntimeException( 'boom' );

		$logger->error(
			'Install failed.',
			array(
				'exception' => $throwable,
				'attempts'  => 3,
				'missing'   => null,
			),
		);

		self::assertSame( $throwable, $inner->records[0]['context']['exception'] );
		self::assertSame( 3, $inner->records[0]['context']['attempts'] );
		self::assertArrayHasKey( 'missing', $inner->records[0]['context'] );
		self::assertNull( $inner->records[0]['context']['missing'] );
	}

	public function test_delegates_level_message_and_context_to_the_inner_logger(): void {
		$inner  = new RecordingLogger();
		$logger = new RedactingLogger( $inner );

		$logger->log( LogLevel::WARNING, 'Warning {code}', array( 'code' => 'E1' ) );

		self::assertSame( LogLevel::WARNING, $inner->records[0]['level'] );
		self::assertSame( 'Warning {code}', $inner->records[0]['message'] );
		self::assertSame( array( 'code' => 'E1' ), $inner->records[0]['context'] );
	}
}

final class RecordingLogger extends AbstractLogger {
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
