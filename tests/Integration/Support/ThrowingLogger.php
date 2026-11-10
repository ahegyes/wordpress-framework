<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Support;

use Psr\Log\AbstractLogger as PsrAbstractLogger;

final class ThrowingLogger extends PsrAbstractLogger {
	public function __construct(
		protected \Throwable $failure
	) {}

	#[\Override]
	public function log( mixed $level, string|\Stringable $message, array $context = array() ): void {
		throw $this->failure;
	}
}
