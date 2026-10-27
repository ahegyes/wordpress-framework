# wp-framework-settings

Settings screens and edit-screen fields declared as data, so WordPress's own APIs render, validate and save them and a plugin never hand-writes form handling.

## Requirements

It builds on wp-framework-core, which brings wp-framework-bootstrap and wp-framework-shared. Its `composer.json` holds its PHP floor under `require.php` and its WordPress floor under `extra.requires-wp`, which a plugin's `Requires PHP` and `Requires at least` headers meet.

## Installation

The packages are not on Packagist, and Composer reads repositories only from the plugin's own `composer.json`, so a plugin lists one `vcs` repository for each package of the stack. It requires the package for development only, because it ships a prefixed copy instead:

```json
{
  "require-dev": {
    "ahegyes/wp-framework-settings": "^2.0"
  },
  "repositories": [
    { "type": "vcs", "url": "https://github.com/ahegyes/wp-framework-bootstrap" },
    { "type": "vcs", "url": "https://github.com/ahegyes/wp-framework-shared" },
    { "type": "vcs", "url": "https://github.com/ahegyes/wp-framework-core" },
    { "type": "vcs", "url": "https://github.com/ahegyes/wp-framework-settings" }
  ],
  "minimum-stability": "dev",
  "prefer-stable": true
}
```

`prefer-stable` installs the newest release that satisfies `^2.0`, and `minimum-stability` lets Composer take the development branch where no release does.

## Prefixing

WordPress loads every plugin into one PHP process, so each plugin ships the framework prefixed under its own namespace. Two plugins that ship the packages unprefixed fatal with "Cannot redeclare class …" once both load bootstrap's files, and their autoloaded classes resolve to whichever plugin's autoloader registered first, so one plugin silently runs the other's copy. WordPress, WooCommerce (`WC_*`, `wc_*`, `WC()`, `Automattic\WooCommerce\*`) and Action Scheduler (`as_*`, `ActionScheduler*`) symbols stay global, and `Psr\*` is prefixed with the framework, so the plugin's container and logger implement the same prefixed interfaces.

[wordpress-configs](https://github.com/ahegyes/wordpress-configs) prefixes the packages with php-scoper into `vendor-prefixed/` after every development install, and a later `composer install --no-dev` keeps that output, so a build runs both, in that order. It also replaces the framework's text domain with the plugin's `extra.text-domain`, failing the build if any of the framework's survives. The plugin adds to its `composer.json`:

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
    "scoping-prefix": "Acme\\PersonaSettings\\Scoped",
    "text-domain": "acme-persona-settings"
  }
}
```

Its `scoper.inc.php` names the packages to prefix:

```php
return ( require __DIR__ . '/vendor/ahegyes/wordpress-configs/php/php-scoper/scoper-base.inc.php' )( __DIR__, 'ahegyes/wp-framework-*' );
```

## Usage

An `OptionsPage` stores each tab's fields in one option through the Settings API, and an `ObjectForm` stores each field on the edited object through a store. The plugin's main file requires `load.php` from bootstrap's prefixed copy, then its autoloader once the floor check passes:

```php
use Acme\PersonaSettings\Scoped\DeepWebSolutions\Framework\Bootstrap\Requirements;

defined( 'ABSPATH' ) || exit;

$acme_ps_bootstrap = __DIR__ . '/vendor-prefixed/ahegyes/wp-framework-bootstrap/load.php';
if ( ! is_readable( $acme_ps_bootstrap ) ) { // The floor gate lives in the bootstrap package, so nothing built may load without it.
	return;
}

require_once $acme_ps_bootstrap;
if ( ! Requirements::check_floors( __FILE__ ) ) {
	return;
}

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/functions.php';
```

The helpers need no kernel. On `plugins_loaded` at priority 15 the plugin registers its settings page:

```php
use Acme\PersonaSettings\Settings;
use Acme\PersonaSettings\Scoped\DeepWebSolutions\Framework\Settings\AdminPage\OptionsPage;

defined( 'ABSPATH' ) || exit;

add_action( 'plugins_loaded', 'acme_ps_register_settings_page', 15 );

// ...

function acme_ps_register_settings_page(): void {
	new OptionsPage( Settings::PREFIX, new Settings() )->register_hooks();
}
```

A definition object declares the page and its fields as data. A field's `validate` callback returns the value to store, or a `WP_Error` whose message the page shows while the stored value stays:

```php
final class Settings implements OptionsPageDefinitionInterface {
	// ...

	#[\Override]
	public function get_options_page( string $page_id ): array {
		return array(
			'parent'     => 'options-general.php',
			'capability' => 'manage_options',
			'page_title' => __( 'Acme Persona Settings', 'acme-persona-settings' ),
			'menu_title' => __( 'Acme Persona Settings', 'acme-persona-settings' ),
			'tabs'       => array(
				array(
					'slug'   => 'general',
					'title'  => __( 'General', 'acme-persona-settings' ),
					'option' => self::PREFIX . '_settings',
					'fields' => array(
						array(
							'type'     => 'number',
							'id'       => 'limit',
							'title'    => __( 'Limit', 'acme-persona-settings' ),
							'validate' => array( $this, 'validate_limit' ),
						),
					),
				),
			),
		);
	}

	// ...

	public function validate_limit( mixed $raw ): int|\WP_Error {
		$limit = \filter_var( $raw, \FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );

		return false === $limit ? new \WP_Error( 'acme_ps_limit', __( 'Enter a whole number of at least 1.', 'acme-persona-settings' ) ) : $limit;
	}

	// ...
}
```

## Contract and versioning

The five packages release together under one version, which the [changelog](https://github.com/ahegyes/wordpress-framework/blob/trunk/CHANGELOG.md) records.

The 2.x contract is every public class, interface, enum, method and constant, the protected members of non-final classes, and the hook and priority each public callback method is registered on, unless tagged `@internal`. `@internal`, `private` and protected-on-final members are not contract, and an `@internal` symbol serves only its own package, because a caret edge installs a package beside an older or newer release of another. Hook names and their arguments, option and meta keys, REST routes and the path of bootstrap's `load.php` are contract too.

Releases follow SemVer on this contract from the first tag: a break of it ships as the next major, and internals change in any release. Plugins on different majors run side by side, because each ships its own prefixed copy.

This package is developed in the [wordpress-framework](https://github.com/ahegyes/wordpress-framework) monorepo, which holds its issues and pull requests.
