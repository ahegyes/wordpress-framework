<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Schema\Field;

/**
 * Machine-readable reason a field submission was rejected.
 *
 * Carried by {@see Errors\FieldProcessingError} so a caller can branch on the cause — and map it
 * to its own message — without parsing prose. The closed set covers every rejection a processor
 * raises: a value of the wrong shape, a choice outside its options, or a value a validator refused.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
enum FieldProcessingErrorReason: string {
	case UnexpectedShape  = 'unexpected_shape';
	case NotAnOption      = 'not_an_option';
	case FailedValidation = 'failed_validation';
}
