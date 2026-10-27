<?php declare( strict_types=1 );
/**
 * Prints, as JSON, what the scoped shared package returns in plain PHP: a parsed and compared version, both branches of a result and the equality of value objects.
 *
 * @package Acme\PersonaShared
 */

use Acme\PersonaShared\Point;
use Acme\PersonaShared\UnknownKey;
use Acme\PersonaShared\Scoped\DeepWebSolutions\Framework\Shared\Result\Failure;
use Acme\PersonaShared\Scoped\DeepWebSolutions\Framework\Shared\Result\Success;
use Acme\PersonaShared\Scoped\DeepWebSolutions\Framework\Shared\Versioning\Version;

require __DIR__ . '/vendor/autoload.php';

echo \json_encode(
	array(
		'wordpress' => \defined( 'ABSPATH' ),
		'version'   => array( (string) Version::from_string( '2.1.0-rc.1' ), Version::from_string( '2.1.0' )->compare( Version::from_string( '2.1.0-rc.1' ) ) ),
		'results'   => array(
			Success::from( 'acme' )->match( static fn ( string $value ): string => "found $value", static fn (): string => 'failed' ),
			Failure::from( new UnknownKey( 'acme' ) )->match( static fn (): string => 'found', static fn ( UnknownKey $error ): string => "unknown $error->key" ),
		),
		'equality'  => array( new Point( 1, 2 )->equals( new Point( 1, 2 ) ), new Point( 1, 2 )->equals( new Point( 2, 1 ) ) ),
	),
	\JSON_THROW_ON_ERROR
);
