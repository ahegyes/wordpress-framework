<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Tests\Unit\Schema\Errors;

use DeepWebSolutions\Framework\Settings\Schema\Errors\FieldProcessingError;
use DeepWebSolutions\Framework\Settings\Schema\Field\FieldProcessingErrorReason;
use DeepWebSolutions\Framework\Shared\ValueObject\AbstractValueObject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesFunction;
use PHPUnit\Framework\TestCase;

#[CoversClass( FieldProcessingError::class )]
#[UsesClass( AbstractValueObject::class )]
#[UsesClass( FieldProcessingErrorReason::class )]
#[UsesFunction( 'DeepWebSolutions\Framework\Shared\Reflection\get_public_property_names' )]
#[UsesFunction( 'DeepWebSolutions\Framework\Shared\Reflection\convert_to_primitives' )]
final class FieldProcessingErrorTest extends TestCase {
	public function test_construction_round_trips_the_field_and_the_reason(): void {
		$error = new FieldProcessingError( 'note', FieldProcessingErrorReason::FailedValidation );

		self::assertSame( 'note', $error->field_id );
		self::assertSame( FieldProcessingErrorReason::FailedValidation, $error->reason );
	}

	public function test_equals_is_structural_over_field_and_reason(): void {
		$error = new FieldProcessingError( 'note', FieldProcessingErrorReason::FailedValidation );

		self::assertTrue( $error->equals( new FieldProcessingError( 'note', FieldProcessingErrorReason::FailedValidation ) ) );
		self::assertFalse( $error->equals( new FieldProcessingError( 'note', FieldProcessingErrorReason::NotAnOption ) ) );
		self::assertFalse( $error->equals( new FieldProcessingError( 'color', FieldProcessingErrorReason::FailedValidation ) ) );
	}

	public function test_json_serialization_reduces_the_reason_to_its_backing_value(): void {
		$error = new FieldProcessingError( 'note', FieldProcessingErrorReason::UnexpectedShape );

		self::assertSame(
			array(
				'field_id' => 'note',
				'reason'   => 'unexpected_shape',
			),
			$error->jsonSerialize(),
		);
	}
}
