# wordpress-framework

A modern, modular framework for building WordPress plugins. Composer-only library, distributed under GPL-2.0-or-later.

## Architecture

Monorepo publishing four Composer packages:

| Package                            | Purpose                                                                                                       | PHP min |
| ---------------------------------- | ------------------------------------------------------------------------------------------------------------- | ------- |
| `ahegyes/wp-framework-bootstrap`   | Pre-autoload PHP/WP version check; gracefully fails with admin notice when runtime can't host the framework.  | 5.6     |
| `ahegyes/wp-framework-core`        | `PluginKernel`, lifecycle interfaces, settings abstraction, DDD-lite primitives.                              | 8.5     |
| `ahegyes/wp-framework-utilities`   | Hooks, caching, admin notices, shortcodes, runtime dependency checks.                                         | 8.5     |
| `ahegyes/wp-framework-woocommerce` | WooCommerce-specific helpers; PSR-3 logger.                                                                   | 8.5     |

The `bootstrap` package runs before any modern PHP 8.5+ code parses, so consumer plugins on incompatible runtimes get a graceful admin notice instead of a fatal error.

## Requirements

- **PHP**: 8.5+ (the bootstrap package itself is PHP 5.6-compatible)
- **WordPress**: 7.0+
- **Docker**: required for integration tests (via wp-env)
- **Node.js**: 24+ (for wp-env CLI)

## Local development

```bash
composer install            # PHP deps
npm install                 # Node deps (wp-env)
npm run wp-env:start        # Start Docker WP environment (~40s first time)
composer quality-check      # Fast: lint + unit tests (no Docker)
composer test:integration   # Real WP via wp-env
composer quality-check:all  # Full: lint + unit + integration + mutation
npm run wp-env:stop         # Stop wp-env when done
```

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
- **Integration tests** (`packages/*/tests/Integration/`) — run inside wp-env's `tests-cli` container with full WordPress loaded. Real `WP_Error`, `get_plugin_data`, `add_action`, etc.
- **Mutation tests** — Infection validates test-suite quality. Currently 100% MSI on the framework slice.

Both unit and integration test suites share a single `tests/bootstrap.php` that conditionally loads WordPress when running inside the wp-env container.
