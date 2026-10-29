# wp-framework-bootstrap

The code a WordPress plugin runs before its autoloader. It parses on PHP 7.4, so a site below the plugin's floors gets a notice instead of a fatal error and still receives the release that fixes it.

## Requirements

It requires no other framework package. Its `composer.json` holds its PHP floor under `require.php` and its WordPress floor, which its updater needs, under `extra.requires-wp`; a plugin's `Requires PHP` and `Requires at least` headers meet them. A plugin's own floors are usually higher, and the floor gate runs below them, which is its purpose.

## Installation

The packages are not on Packagist, and Composer reads repositories only from the plugin's own `composer.json`, so a plugin lists the package's `vcs` repository. It requires the package for development only, because it ships a prefixed copy instead:

```json
{
  "require-dev": {
    "ahegyes/wp-framework-bootstrap": "^2.0"
  },
  "repositories": [
    { "type": "vcs", "url": "https://github.com/ahegyes/wp-framework-bootstrap" }
  ],
  "minimum-stability": "dev",
  "prefer-stable": true
}
```

`prefer-stable` installs the newest release that satisfies `^2.0`, and `minimum-stability` lets Composer take the development branch where no release does.

## Prefixing

WordPress loads every plugin into one PHP process, so each plugin ships the package prefixed under its own namespace. Two plugins that ship it unprefixed fatal with "Cannot redeclare class …" once both load it. WordPress symbols stay global.

[wordpress-configs](https://github.com/ahegyes/wordpress-configs) prefixes the package with php-scoper into `vendor-prefixed/` after every development install, and a later `composer install --no-dev` keeps that output, so a build runs both, in that order. The package loads by path, never through an autoloader, so the plugin adds to its `composer.json`:

```json
{
  "require-dev": {
    "ahegyes/wordpress-configs": "dev-trunk",
    "humbug/php-scoper": "^0.18"
  },
  "repositories": [
    { "type": "vcs", "url": "https://github.com/ahegyes/wordpress-configs" }
  ],
  "scripts": {
    "post-autoload-dump": [ "DeepWebSolutions\\Config\\Composer\\ScopePhpDependencies::postAutoloadDump" ]
  },
  "extra": {
    "scoping-prefix": "Acme\\PersonaBootstrap\\Scoped"
  }
}
```

Its `scoper.inc.php` names the package to prefix:

```php
return ( require __DIR__ . '/vendor/ahegyes/wordpress-configs/php/php-scoper/scoper-base.inc.php' )( __DIR__, 'ahegyes/wp-framework-*' );
```

## Usage

The plugin's main file requires `load.php` from the prefixed copy, registers the updater and returns on a site below the plugin's floors, where WordPress core's own requirements message shows to users who can activate plugins:

```php
use Acme\PersonaBootstrap\Scoped\DeepWebSolutions\Framework\Bootstrap\Requirements;
use Acme\PersonaBootstrap\Scoped\DeepWebSolutions\Framework\Bootstrap\Updater;

defined( 'ABSPATH' ) || exit;

$acme_pb_bootstrap = __DIR__ . '/vendor-prefixed/ahegyes/wp-framework-bootstrap/load.php';
if ( ! is_readable( $acme_pb_bootstrap ) ) { // The floor gate lives in the bootstrap package, so nothing built may load without it.
	return;
}

require_once $acme_pb_bootstrap;
Updater::register( __FILE__ ); // Before the floor gate, so a release that lowers the floors still reaches a site below them.
if ( ! Requirements::check_floors( __FILE__ ) ) {
	return;
}

require_once __DIR__ . '/functions.php';
```

The updater offers the plugin's GitHub releases through WordPress's own update screens while the plugin's `Update URI` header points at its github.com repository. It installs the release asset named after the plugin's text domain, such as `acme-persona-bootstrap.zip`, and the `dws_github_updater_config_{text-domain}` filter changes the repository, the asset or the channel, or turns updates off. It offers only tags such as `v2.1.0` or `v2.1.0-rc.1`, whose optional suffix is `-dev`, `-alpha`, `-beta` or `-rc` in any letter case, optionally numbered, because PHP's `version_compare()` ranks other suffixes above their release.

## Contract and versioning

The five packages release together under one version, which the [changelog](https://github.com/ahegyes/wordpress-framework/blob/trunk/CHANGELOG.md) records.

The 2.x contract is every public class, interface, enum, method and constant, the protected members of non-final classes, and the hook and priority each public callback method is registered on, unless tagged `@internal`. `@internal`, `private` and protected-on-final members are not contract, and an `@internal` symbol serves only its own package, because a caret edge installs a package beside an older or newer release of another. Hook names and their arguments, option and meta keys, REST routes and the path of bootstrap's `load.php` are contract too.

Releases follow SemVer on this contract from the first tag: a break of it ships as the next major, and internals change in any release. Plugins on different majors run side by side, because each ships its own prefixed copy.

This package is developed in the [wordpress-framework](https://github.com/ahegyes/wordpress-framework) monorepo, which holds its issues and pull requests.
