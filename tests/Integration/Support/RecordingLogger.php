<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Support;

use Psr\Log\AbstractLogger as PsrAbstractLogger;

final class RecordingLogger extends PsrAbstractLogger {
	public array $records = array();

	#[\Override]
	public function log( mixed $level, string|\Stringable $message, array $context = array() ): void {
		$this->records[] = array( $level, (string) $message, $context );
	}
}
