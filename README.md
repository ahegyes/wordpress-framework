# wordpress-framework

A modular framework for WordPress plugins that read as hand-written WordPress code: five packages, each building only on the one below it, with each plugin shipping its own scoped copy.

## Architecture

Monorepo publishing five Composer packages:

| Package | Purpose |
| --- | --- |
| `ahegyes/wp-framework-bootstrap` | The code a WordPress plugin runs before its autoloader. It parses on PHP 7.4, so a site below the plugin's floors gets a notice instead of a fatal error and still receives the release that fixes it. |
| `ahegyes/wp-framework-shared` | The WordPress-free building blocks every framework package shares, kept portable enough to work unchanged in any PHP project. |
| `ahegyes/wp-framework-core` | The plugin kernel. A plugin is composed of small components that load only where they apply, on WordPress's own APIs, with the infrastructure WordPress leaves every plugin to rebuild. |
| `ahegyes/wp-framework-settings` | Settings screens and edit-screen fields declared as data, so WordPress's own APIs render, validate and save them and a plugin never hand-writes form handling. |
| `ahegyes/wp-framework-woocommerce` | The framework's settings, edit-screen and logging helpers carried onto WooCommerce's own screens and APIs, for plugins that extend WooCommerce. |

The packages form a stack: core builds on bootstrap and shared, settings on core, and woocommerce on settings, and shared also installs alone.

## Requirements

- **PHP**: 8.5 or later (the bootstrap package itself parses on PHP 7.4)
- **WordPress**: 7.1 or later
- **Node.js**: 26 or later with npm 11 or later (for wp-env CLI)
- **Docker**: for the wp-env environments

## Local development

```sh
composer packages-install   # PHP deps (wraps composer install, ignoring only the PHP upper bound)
npm install                 # Node deps (wp-env)
npm run wp-env:start        # developer environment at http://localhost:8800, as local, with WooCommerce
composer quality-check      # Validation, PHPCS, PHPStan, Unit and Integration tests (starts the tests environment)
```

## Composer scripts

| Script | Runs |
| --- | --- |
| `test:unit` | Unit tests (no WordPress) |
| `test:integration` | Starts the tests environment, then runs `test:integration:run` |
| `fixtures:build` | Builds the fixture plugin: a development install that scopes the framework into it, PHPStan over its code, then its production install |
| `test:integration:run` | Builds the fixture plugin, then runs the Integration tests in the running tests environment: single site with WooCommerce, as production, without the `multisite` and `without-woocommerce` groups |
| `test:integration:multisite` | Starts the multisite environment, then runs `test:integration:multisite:run` |
| `test:integration:multisite:run` | Builds the fixture plugin, then runs the `multisite` and `without-woocommerce` groups in the running multisite environment: a network without WooCommerce, as production |
| `test` | Unit and Integration tests |
| `lint:php` | PHPCS over production code and tests, and PHPStan |
| `format:php`, `format:php:tests` | Auto-fix code style (PHPCBF) |
| `quality-check` | Manifest validation, lint, Unit and Integration tests |

## Testing strategy

- **Unit tests** (`tests/Unit/`) run in plain PHP with no WordPress loaded, including tests that run PHPStan and read the package manifests.
- **Integration tests** (`tests/Integration/`) run inside wp-env's `cli` container against real WordPress, hooks and users, on a single site with WooCommerce, and the `multisite` and `without-woocommerce` groups on a network without it. `test:integration` starts the tests environment before every run, which also applies any change to its config; `test:integration:run` skips that start, as CI does after starting the environment itself.
- **Fixture plugin** (`tests/Fixtures/consumer-a/`) is a WooCommerce plugin built on all five packages. It installs them through its own `composer.json`, scopes them with wordpress-configs and passes PHPStan on its own code; the Integration tests copy its production build into the plugins directory and run it through wp-admin requests and WP-CLI, whole and with files missing.
- **Type tests** (`tests/PHPStan/`) are files PHPStan analyzes during `lint:php`: `assertType()` calls pin inferred types, and each expected error carries a `@phpstan-ignore` naming its identifier, so the run fails when the error disappears.
