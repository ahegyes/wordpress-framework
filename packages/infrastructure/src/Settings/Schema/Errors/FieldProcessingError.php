<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Schema\Errors;

use DeepWebSolutions\Framework\Settings\Schema\Field\FieldProcessingErrorReason;
use DeepWebSolutions\Framework\Shared\Error\ErrorInterface;
use DeepWebSolutions\Framework\Shared\ValueObject\AbstractValueObject;

/**
 * Value object for a rejected field submission's failure payload, carried by a {@see \DeepWebSolutions\Framework\Shared\Result\Failure}.
 *
 * Names the field whose submitted value a processing step rejected and the machine-readable reason —
 * a value of the wrong shape, a choice outside its options, or a value a validator refused — so a
 * backend can preserve the field's prior value, surface the rejection, and map the cause to its own
 * message rather than overwrite the field with an empty one.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final readonly class FieldProcessingError extends AbstractValueObject implements ErrorInterface {
	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string                     $field_id Id of the field whose submission was rejected.
	 * @param   FieldProcessingErrorReason $reason   Machine-readable cause of the rejection.
	 */
	public function __construct(
		public string $field_id,
		public FieldProcessingErrorReason $reason,
	) {}

	// endregion
}
