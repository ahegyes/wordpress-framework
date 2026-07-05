<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Schema\Field;

/**
 * The framework's settings field-type taxonomy.
 *
 * The closed set of field types the render and processing layers understand. A
 * field declares its type as a string; the processing layer resolves it against
 * this enum and rejects an unrecognized token.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
enum FieldType: string {
	case Text        = 'text';
	case Number      = 'number';
	case Email       = 'email';
	case Url         = 'url';
	case Textarea    = 'textarea';
	case Select      = 'select';
	case Multiselect = 'multiselect';
	case Checkbox    = 'checkbox';
	case Radio       = 'radio';
}
