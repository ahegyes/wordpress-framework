<?php declare( strict_types=1 );
/**
 * Prints, as JSON, what the scoped shared package returns in plain PHP: a parsed and compared version, a rejected one, both branches of a result, a narrowed result and the equality of value objects.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @package Acme\PersonaShared
 */

use Acme\PersonaShared\Catalog;
use Acme\PersonaShared\Point;
use Acme\PersonaShared\UnknownKey;
use Acme\PersonaShared\Scoped\DeepWebSolutions\Framework\Shared\Exception\ExceptionInterface;
use Acme\PersonaShared\Scoped\DeepWebSolutions\Framework\Shared\Result\Failure;
use Acme\PersonaShared\Scoped\DeepWebSolutions\Framework\Shared\Result\Success;
use Acme\PersonaShared\Scoped\DeepWebSolutions\Framework\Shared\Versioning\Version;

require __DIR__ . '/vendor/autoload.php';

try {
	$acme_rejected = (string) Version::from_string( 'next' );
} catch ( ExceptionInterface $acme_exception ) {
	$acme_rejected = $acme_exception::class;
}

$acme_catalog = new Catalog( array( 'acme' => 'box' ) );

echo \json_encode(
	array(
		'wordpress' => \defined( 'ABSPATH' ),
		'version'   => array( (string) Version::from_string( '2.1.0-rc.1' ), Version::from_string( '2.1.0' )->compare( Version::from_string( '2.1.0-rc.1' ) ) ),
		'rejected'  => $acme_rejected,
		'results'   => array(
			Success::from( 'acme' )->match( static fn ( string $value ): string => "found $value", static fn (): string => 'failed' ),
			Failure::from( new UnknownKey( 'acme' ) )->match( static fn (): string => 'found', static fn ( UnknownKey $error ): string => "unknown $error->key" ),
		),
		'narrowed'  => array( $acme_catalog->describe( 'acme' ), $acme_catalog->describe( 'other' ) ),
		'equality'  => array( new Point( 1, 2 )->equals( new Point( 1, 2 ) ), new Point( 1, 2 )->equals( new Point( 2, 1 ) ) ),
	),
	\JSON_THROW_ON_ERROR
);
