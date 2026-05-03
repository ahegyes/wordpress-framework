# wordpress-framework

DWS v2 WordPress framework — monorepo for 4 packages (bootstrap, core, utilities, woocommerce). Auto-splits to `wp-framework-*` mirrors via `.github/workflows/split-packages.yml` (splitsh-lite v1.0.1).

## Status

`bootstrap` + `core` have implementations. `utilities` + `woocommerce` are skeletons (`.gitkeep` only) — to be built per the project memory's hybrid-parallel plan as plugin migration drives demand.

## Monorepo structure

```
wordpress-framework/
├── packages/
│   ├── bootstrap/          # ahegyes/wp-framework-bootstrap (PHP 5.6+ exception)
│   │   └── check-requirements.php  # Pre-autoload PHP/WP version check
│   ├── core/               # ahegyes/wp-framework-core
│   │   └── src/{Contracts,Kernel}/  # PluginKernel + lifecycle interfaces
│   ├── utilities/          # ahegyes/wp-framework-utilities (skeleton)
│   └── woocommerce/        # ahegyes/wp-framework-woocommerce (skeleton)
├── tests/Fixtures/consumer-smoke/  # plugin-template-shaped scoping smoke fixture
├── composer.json                   # path repos for all 4 packages + VCS for wordpress-configs + WP Packages registry for wp-plugin/woocommerce
└── .github/workflows/
```

`wp-plugin/woocommerce` is installed as a dev dep at `vendor/wp-plugin/woocommerce/` for source-grep + integration-test runtime when developing the woocommerce package. Mounted into wp-env via `.wp-env.tests.json` `mappings`.

## Local development

Commands run from monorepo root:

```sh
composer packages-install   # PHP deps (--ignore-platform-reqs wrapped)
npm install                 # wp-env
npm run wp-env:start        # Start WordPress on port 8801
composer quality-check      # lint + unit tests (no Docker)
composer test:integration   # full WP integration tests via wp-env
composer test:mutation      # Infection mutation tests (no Docker)
```

`composer lint:php` aggregates PHPCS + PHPStan + **deptrac** (architecture-rule check via `deptrac.yaml`). Cache lives at `tests/.cache/deptrac/`.

wp-env runs on **port 8801** (per workspace port scheme — see `feedback_wp_env_port_scheme` memory).

Per-package install isn't supported during dev: cross-package requires (e.g., core's `ahegyes/wp-framework-bootstrap`) only resolve via the root's path-repo declarations; per-package `require-dev: ahegyes/wordpress-configs` is fetchable only via the root's VCS repo declaration.

Integration tests run in wp-env's `cli` container (the deprecated `tests-cli` sub-env was dropped per Gutenberg PR #75341 — `.wp-env.tests.json` declares `"testsEnvironment": false`).

## Cross-package testing

PHPUnit runs at the root level:

```sh
vendor/bin/phpunit packages/bootstrap/tests/
vendor/bin/phpunit --testsuite=Unit
```

Integration via `composer test:integration` → `npm run test:integration` → `wp-env run cli vendor/bin/phpunit --testsuite=Integration`.

## Releases

`.github/workflows/split-packages.yml` runs on push to `trunk`. splitsh-lite splits each `packages/<X>/` subdirectory and force-pushes to `github.com/ahegyes/wp-framework-<X>` via `MONOREPO_SPLIT_TOKEN` PAT.

Per-package changelogger fragments live in `packages/<X>/changelog/` and aggregate to `packages/<X>/CHANGELOG.md` on `composer changelog:write:<package>`.
