<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Shared\Error;

use DeepWebSolutions\Framework\Shared\Exception\ExceptionInterface;
use DeepWebSolutions\Framework\Shared\Result\Failure;

/**
 * Marker for expected failure outcomes — domain-rule violations, validation
 * failures, business-invariant breaches. Callers handle these as data, typically
 * via {@see Failure}.
 *
 * Contrast with {@see ExceptionInterface}: exceptions signal unexpected failures
 * (infrastructure outages, programmer errors) and propagate via `throw`/`catch`.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
interface ErrorInterface {}
