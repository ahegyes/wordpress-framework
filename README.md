# wordpress-framework

A modern, modular framework for building WordPress plugins. Composer-only library, distributed under GPL-2.0-or-later.

## Architecture

Monorepo publishing five Composer packages:

| Package | Purpose |
| --- | --- |
| `ahegyes/wp-framework-bootstrap` | PHP and WordPress floor gate and GitHub release updater, loaded by path before any autoloader. |
| `ahegyes/wp-framework-shared` | WordPress-free PHP primitives: framework exceptions, results, value objects and versions. |
| `ahegyes/wp-framework-core` | Plugin kernel: a gated component tree on a PSR-11 container, a boot error boundary, migrations, background scheduling, option and meta rows with conditional writes, admin notices, role capability grants, PSR-16 transient and object caches and an uninstall engine. |
| `ahegyes/wp-framework-settings` | Options pages on the Settings API, form controls and value sanitization. |
| `ahegyes/wp-framework-woocommerce` | WooCommerce settings tabs and sections, product data tabs, a requirement check, a PSR-3 logger and order meta cleanup. |

The packages form a stack: core builds on bootstrap and shared, settings on core, and woocommerce on settings, and shared also installs alone.

The `bootstrap` package parses on older PHP versions, so a plugin on an incompatible runtime gets an admin notice instead of a fatal error.

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
| `test:integration:run` | Integration tests in the running tests environment: single site with WooCommerce, as production, without the `multisite` and `without-woocommerce` groups |
| `test:integration:multisite` | Starts the multisite environment, then runs `test:integration:multisite:run` |
| `test:integration:multisite:run` | The `multisite` and `without-woocommerce` groups in the running multisite environment: a network without WooCommerce, as production |
| `test` | Unit and Integration tests |
| `lint:php` | PHPCS over production code and tests, and PHPStan |
| `format:php`, `format:php:tests` | Auto-fix code style (PHPCBF) |
| `quality-check` | Manifest validation, lint, Unit and Integration tests |

## Testing strategy

- **Unit tests** (`tests/Unit/`) run in plain PHP with no WordPress loaded, including tests that run PHPStan and read the package manifests.
- **Integration tests** (`tests/Integration/`) run inside wp-env's `cli` container against real WordPress, hooks and users, on a single site with WooCommerce, and the `multisite` and `without-woocommerce` groups on a network without it. `test:integration` starts the tests environment before every run, which also applies any change to its config; `test:integration:run` skips that start, as CI does after starting the environment itself.
- **Type tests** (`tests/PHPStan/`) are files PHPStan analyzes during `lint:php`: `assertType()` calls pin inferred types, and each expected error carries a `@phpstan-ignore` naming its identifier, so the run fails when the error disappears.
