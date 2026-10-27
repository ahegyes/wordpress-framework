# wp-framework-shared

The WordPress-free building blocks every framework package shares, kept portable enough to work unchanged in any PHP project.

## Requirements

It requires no other framework package and no WordPress. Its `composer.json` holds its PHP floor under `require.php`.

## Installation

The packages are not on Packagist, and Composer reads repositories only from the project's own `composer.json`, so a project lists the package's `vcs` repository. It requires the package for development only, because it ships a prefixed copy instead:

```json
{
  "require-dev": {
    "ahegyes/wp-framework-shared": "^2.0"
  },
  "repositories": [
    { "type": "vcs", "url": "https://github.com/ahegyes/wp-framework-shared" }
  ],
  "minimum-stability": "dev",
  "prefer-stable": true
}
```

`prefer-stable` installs the newest release that satisfies `^2.0`, and `minimum-stability` lets Composer take the development branch where no release does.

## Prefixing

A project that runs beside other copies of the package, as every WordPress plugin does, ships it prefixed under its own namespace, because unprefixed copies resolve to one of them, chosen by the order their autoloaders register in, so the other projects silently run it.

[wordpress-configs](https://github.com/ahegyes/wordpress-configs) prefixes the package with php-scoper into `vendor-prefixed/` after every development install, and a later `composer install --no-dev` keeps that output, so a build runs both, in that order. The project adds to its `composer.json`:

```json
{
  "require-dev": {
    "ahegyes/wordpress-configs": "dev-trunk",
    "humbug/php-scoper": "^0.18"
  },
  "repositories": [
    { "type": "vcs", "url": "https://github.com/ahegyes/wordpress-configs" }
  ],
  "autoload": {
    "files": [ "vendor-prefixed/scoper-autoload.php" ]
  },
  "scripts": {
    "post-autoload-dump": [ "DeepWebSolutions\\Config\\Composer\\ScopePhpDependencies::postAutoloadDump" ]
  },
  "extra": {
    "scoping-prefix": "Acme\\PersonaShared\\Scoped"
  }
}
```

Its `scoper.inc.php` names the package to prefix:

```php
return ( require __DIR__ . '/vendor/ahegyes/wordpress-configs/php/php-scoper/scoper-base.inc.php' )( __DIR__, 'ahegyes/wp-framework-*' );
```

## Usage

A `Version` parses and compares versions, a result is a `Success` or a `Failure` whose `match()` takes one callback per branch, and a value object equals any other of its class with the same values. A script loads the project's autoloader and imports the package under the project's prefix:

```php
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
```

A failure carries an error object, and a value object declares its values as promoted properties:

```php
final readonly class UnknownKey implements ErrorInterface {
	// ...

	public function __construct(
		public string $key
	) {}

	// ...
}
```

```php
final readonly class Point extends AbstractValueObject {
	// ...

	public function __construct(
		public int $x,
		public int $y
	) {}

	// ...
}
```

## Contract and versioning

The five packages release together under one version, which the [changelog](https://github.com/ahegyes/wordpress-framework/blob/trunk/CHANGELOG.md) records.

The 2.x contract is every public class, interface, enum, method and constant, the protected members of non-final classes, and the hook and priority each public callback method is registered on, unless tagged `@internal`. `@internal`, `private` and protected-on-final members are not contract, and an `@internal` symbol serves only its own package, because a caret edge installs a package beside an older or newer release of another. Hook names and their arguments, option and meta keys, REST routes and the path of bootstrap's `load.php` are contract too.

Releases follow SemVer on this contract from the first tag: a break of it ships as the next major, and internals change in any release. Plugins on different majors run side by side, because each ships its own prefixed copy.

This package is developed in the [wordpress-framework](https://github.com/ahegyes/wordpress-framework) monorepo, which holds its issues and pull requests.
