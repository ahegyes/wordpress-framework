<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Unit\Scheduling\Errors;

use DeepWebSolutions\Framework\Utilities\Scheduling\Errors\SchedulingError;
use DeepWebSolutions\Framework\Utilities\Scheduling\SchedulingErrorReason;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( SchedulingError::class )]
#[UsesClass( SchedulingErrorReason::class )]
final class SchedulingErrorTest extends TestCase {
	public function test_exposes_reason_message_and_default_empty_context(): void {
		$error = new SchedulingError( SchedulingErrorReason::InvalidInterval, 'Interval must be positive.' );

		self::assertSame( SchedulingErrorReason::InvalidInterval, $error->reason );
		self::assertSame( 'Interval must be positive.', $error->message );
		self::assertSame( array(), $error->context );

		$with_context = new SchedulingError(
			SchedulingErrorReason::UnsupportedGroup,
			'No groups.',
			array( 'group' => 'reports' ),
		);

		self::assertSame( array( 'group' => 'reports' ), $with_context->context );
	}
}
