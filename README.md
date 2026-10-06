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

## Local development

```bash
composer packages-install   # PHP deps (wraps composer install, ignoring only the PHP upper bound)
npm install                 # Node deps (wp-env)
composer quality-check      # PHPCS, PHPStan and unit tests
```

## Composer scripts

| Script | Runs |
| --- | --- |
| `test:unit` | Unit tests (no WordPress) |
| `test` | Every test suite |
| `lint:php` | PHPCS over production code and tests, and PHPStan |
| `format:php`, `format:php:tests` | Auto-fix code style (PHPCBF) |
| `quality-check` | Lint and every test suite |

## Testing strategy

- **Unit tests** (`tests/Unit/`) run in plain PHP with no WordPress loaded, including tests that run PHPStan and read the package manifests.

## Lineage

Successor to the archived DWS v1 framework packages under the [`deep-web-solutions` GitHub org](https://github.com/orgs/deep-web-solutions/repositories?q=wordpress-framework).
