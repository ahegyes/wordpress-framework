# wordpress-framework

A modern, modular framework for building WordPress plugins. Composer-only library, distributed under GPL-2.0-or-later.

## Architecture

Monorepo publishing five Composer packages:

| Package | Purpose |
| --- | --- |
| `ahegyes/wp-framework-bootstrap` | Requirements gate, GitHub release updater and uninstall engine, loaded by path before any autoloader. |
| `ahegyes/wp-framework-shared` | WordPress-free PHP primitives: framework exceptions, results, value objects, versions and helper functions. |
| `ahegyes/wp-framework-core` | Plugin kernel: a gated component tree on a PSR-11 container, a boot error boundary, migrations and admin notices. |
| `ahegyes/wp-framework-settings` | Options pages on the Settings API, form controls and value sanitization. |
| `ahegyes/wp-framework-woocommerce` | WooCommerce settings tabs and sections, product data tabs, a requirement check, a PSR-3 logger and order meta cleanup. |

The `bootstrap` package parses on older PHP versions, so a plugin on an incompatible runtime gets an admin notice instead of a fatal error.

## Requirements

- **PHP**: 8.5 or later (the bootstrap package itself parses on PHP 7.4)
- **WordPress**: 7.1 or later
- **Node.js**: 26 or later with npm 11 or later (for wp-env CLI)
- **Docker**: for the wp-env Integration environments

## Local development

```sh
composer packages-install   # PHP deps (wraps composer install, ignoring only the PHP upper bound)
npm install                 # Node deps (wp-env)
composer quality-check      # PHPCS, PHPStan, Unit and Integration tests (starts wp-env)
```

## Composer scripts

| Script | Runs |
| --- | --- |
| `test:unit` | Unit tests (no WordPress) |
| `test:integration` | Integration tests in wp-env, single site with WooCommerce, as production |
| `test:integration:multisite` | The `multisite` group on a network |
| `test` | Unit and Integration tests |
| `lint:php` | PHPCS over production code and tests, and PHPStan |
| `format:php`, `format:php:tests` | Auto-fix code style (PHPCBF) |
| `quality-check` | Lint, Unit and Integration tests |

## Testing strategy

- **Unit tests** (`tests/Unit/`) run in plain PHP with no WordPress loaded, including tests that run PHPStan and read the package manifests.
- **Integration tests** (`tests/Integration/`) run inside wp-env's `cli` container against real WordPress, hooks and users, on a single site with WooCommerce and on a network. Each lane starts its environment when it is not running.
- **Type tests** (`tests/PHPStan/`) are files PHPStan analyzes during `lint:php`: `assertType()` calls pin inferred types, and each expected error carries a `@phpstan-ignore` naming its identifier, so the run fails when the error disappears.
