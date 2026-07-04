# wordpress-framework

A modern, modular framework for building WordPress plugins. Composer-only library, distributed under GPL-2.0-or-later.

## Architecture

Monorepo publishing seven Composer packages:

| Package                            | Purpose                                                                          | PHP min |
| ---------------------------------- | -------------------------------------------------------------------------------- | ------- |
| `ahegyes/wp-framework-bootstrap`   | Pre-autoload PHP/WP version check with graceful admin-notice fallback.           | 5.6     |
| `ahegyes/wp-framework-shared`      | Substrate primitives: result/value-object patterns, error/exception scaffolding. | 8.5     |
| `ahegyes/wp-framework-storage`     | Key-value storage backends: in-memory, wp_options, and user-meta.                | 8.5     |
| `ahegyes/wp-framework-core`        | Plugin kernel, feature/lifecycle/installer interfaces, conditional gating.       | 8.5     |
| `ahegyes/wp-framework-utilities`   | Hooks, admin notices, caching, conditionals, scheduling, permissions, logging, helpers. | 8.5     |
| `ahegyes/wp-framework-settings`    | Declarative settings screens; WordPress options and object-field backends.       | 8.5     |
| `ahegyes/wp-framework-woocommerce` | WooCommerce settings backend, product/order-data fields, PSR-3 logger.            | 8.5     |

The `bootstrap` package runs before any modern PHP 8.5+ code parses, so consumer plugins on incompatible runtimes get a graceful admin notice instead of a fatal error.

## Requirements

- **PHP**: 8.5+ (the bootstrap package itself is PHP 5.6-compatible)
- **WordPress**: 7.0+
- **Docker**: required for integration tests (via wp-env)
- **Node.js**: 26+ with npm 11+ (for wp-env CLI)

## Local development

```bash
composer packages-install   # PHP deps (wraps composer install with --ignore-platform-reqs)
npm install                 # Node deps (wp-env)
npm run wp-env:start        # Start Docker WP environment (~40s first time)
composer quality-check      # Lint + unit + integration (boots wp-env)
composer test:integration   # Real WP via wp-env
composer quality-check:all  # Full: lint + unit + integration + mutation
npm run wp-env:stop         # Stop wp-env when done
```

> Always use `composer packages-install` / `composer packages-update` (not bare `composer install` / `update`). The wrappers pass `--ignore-platform-reqs` so composer skips generating `vendor/composer/platform_check.php`, which would otherwise bypass the framework's own runtime check — the bootstrap package's `check_requirements()` (`src/Requirements/functions.php`, loaded via its Composer files autoload) — and emit a hard PHP fatal instead of the friendly admin notice.

## Composer scripts

| Script                | Runs                            | Docker |
| --------------------- | ------------------------------- | ------ |
| `test:unit`           | Unit tests (no WP)              | No     |
| `test:integration`    | Integration tests inside wp-env | Yes    |
| `test:unit:mutation`  | Infection mutation tests        | No     |
| `test`                | Unit + Integration              | Yes    |
| `test:all`            | Unit + Integration + Mutation   | Yes    |
| `lint:php`            | PHPCS + PHPStan + deptrac + composer-require-checker | No     |
| `format:php`          | Auto-fix code style (PHPCBF)    | No     |
| `quality-check`       | Lint + Unit + Integration       | Yes    |
| `quality-check:all`   | Lint + All tests                | Yes    |

## Testing strategy

- **Unit tests** (`packages/*/tests/Unit/`) — pure PHP, no WP loaded. Used to test wrapper "outside WP" fallback paths (e.g., `is_php_compatible` correctly returns `false` when WordPress's native function is missing).
- **Integration tests** (`packages/*/tests/Integration/`) — run inside wp-env's `cli` container with full WordPress loaded. Real `WP_Error`, `get_plugin_data`, `add_action`, etc.
- **Mutation tests** — Infection validates test-suite quality.

Both unit and integration test suites share a single `tests/bootstrap.php` that conditionally loads WordPress when running inside the wp-env container.

## Lineage

Successor to the archived DWS v1 framework packages under the [`deep-web-solutions` GitHub org](https://github.com/orgs/deep-web-solutions/repositories?q=wordpress-framework).
