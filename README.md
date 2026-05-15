# wordpress-framework

A modern, modular framework for building WordPress plugins. Composer-only library, distributed under GPL-2.0-or-later.

## Architecture

Monorepo publishing five Composer packages:

| Package                            | Purpose                                                                          | PHP min |
| ---------------------------------- | -------------------------------------------------------------------------------- | ------- |
| `ahegyes/wp-framework-bootstrap`   | Pre-autoload PHP/WP version check with graceful admin-notice fallback.           | 5.6     |
| `ahegyes/wp-framework-shared`      | Substrate primitives: result/value-object patterns, error/exception scaffolding. | 8.5     |
| `ahegyes/wp-framework-core`        | Plugin kernel, lifecycle and state interfaces, two-pass boot dispatch.           | 8.5     |
| `ahegyes/wp-framework-utilities`   | Hooks, admin notices, and key-value storage backends.                            | 8.5     |
| `ahegyes/wp-framework-woocommerce` | WooCommerce settings backend and WC-aware helpers.                               | 8.5     |

The `bootstrap` package runs before any modern PHP 8.5+ code parses, so consumer plugins on incompatible runtimes get a graceful admin notice instead of a fatal error.

## Requirements

- **PHP**: 8.5+ (the bootstrap package itself is PHP 5.6-compatible)
- **WordPress**: 7.0+
- **Docker**: required for integration tests (via wp-env)
- **Node.js**: 26+ (for wp-env CLI)

## Local development

```bash
composer packages-install   # PHP deps (wraps composer install with --ignore-platform-reqs)
npm install                 # Node deps (wp-env)
npm run wp-env:start        # Start Docker WP environment (~40s first time)
composer quality-check      # Fast: lint + unit tests (no Docker)
composer test:integration   # Real WP via wp-env
composer quality-check:all  # Full: lint + unit + integration + mutation
npm run wp-env:stop         # Stop wp-env when done
```

> Always use `composer packages-install` / `composer packages-update` (not bare `composer install` / `update`). The wrappers pass `--ignore-platform-reqs` so composer skips generating `vendor/composer/platform_check.php`, which would otherwise bypass the framework's own `check-requirements.php` runtime check and emit a hard PHP fatal instead of the friendly admin notice.

## Composer scripts

| Script                | Runs                            | Docker |
| --------------------- | ------------------------------- | ------ |
| `test:unit`           | Unit tests (no WP)              | No     |
| `test:integration`    | Integration tests inside wp-env | Yes    |
| `test:mutation`       | Infection mutation tests        | No     |
| `test`                | Unit + Integration              | Yes    |
| `test:all`            | Unit + Integration + Mutation   | Yes    |
| `lint:php`            | PHPCS + PHPStan + Deptrac       | No     |
| `format:php`          | Auto-fix code style (PHPCBF)    | No     |
| `quality-check`       | Lint + Unit                     | No     |
| `quality-check:all`   | Lint + All tests                | Yes    |

## Testing strategy

- **Unit tests** (`packages/*/tests/Unit/`) — pure PHP, no WP loaded. Used to test wrapper "outside WP" fallback paths (e.g., `is_php_compatible` correctly returns `false` when WordPress's native function is missing).
- **Integration tests** (`packages/*/tests/Integration/`) — run inside wp-env's `cli` container with full WordPress loaded. Real `WP_Error`, `get_plugin_data`, `add_action`, etc.
- **Mutation tests** — Infection validates test-suite quality.

Both unit and integration test suites share a single `tests/bootstrap.php` that conditionally loads WordPress when running inside the wp-env container.

## History

This framework is a ground-up rewrite of the DWS WordPress framework v1, developed at Deep Web Solutions GmbH (now defunct). v1 spread across 7 archived packages (`wordpress-framework-{bootstrapper,helpers,core,utilities,settings,woocommerce,foundations}`) under the [`deep-web-solutions` GitHub org](https://github.com/orgs/deep-web-solutions/repositories?q=wordpress-framework). v2 reorganizes into 5 packages with composition over inheritance, PSR-11 wiring throughout, and a clear substrate-vs-WP-aware boundary between `shared` and the rest.
