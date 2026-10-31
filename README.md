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

The packages form a stack: core builds on bootstrap and shared, settings on core, and woocommerce on settings, and shared also installs alone. Each package's README covers installing, prefixing and using it.

## Requirements

- **PHP**: the floor `composer.json` declares under `require.php`; each package's `composer.json` declares its own, the bootstrap package's lower
- **WordPress**: the highest floor the package manifests declare under `extra.requires-wp`
- **Node.js and npm**: the versions `package.json` declares under `engines` (for wp-env CLI)
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
| `fixtures:build` | Builds both fixture plugins and the five personas: for each, a development install that scopes the framework into it, PHPStan over its code, then its production install |
| `fixtures:build:consumer-a`, `fixtures:build:consumer-b`, `fixtures:build:persona-<name>` | Builds one fixture plugin or persona |
| `test:integration:run` | Builds the fixture plugins and personas, then runs the Integration tests in the running tests environment: single site with WooCommerce, as production, without the `multisite` and `without-woocommerce` groups |
| `test:integration:multisite` | Starts the multisite environment, then runs `test:integration:multisite:run` |
| `test:integration:multisite:run` | Builds the fixture plugins and personas, then runs the `multisite` and `without-woocommerce` groups in the running multisite environment: a network without WooCommerce, as production |
| `test:integration:php74` | Starts the PHP 7.4 environment, then runs `test:integration:php74:run` |
| `test:integration:php74:run` | Builds consumer-a and the bootstrap persona, then runs the `Php74` suite with the host's PHP against the running PHP 7.4 environment: a single site with WordPress 6.3, as production |
| `test:integration:lowest` | Starts the tests environment, then runs `test:integration:lowest:run` |
| `test:integration:lowest:run` | Locks consumer-a's runtime packages at the lowest versions its constraints allow, builds it, then runs its rows in the running tests environment; its rewritten manifest and lock are restored with `git checkout` |
| `test` | Unit and Integration tests |
| `lint:php` | PHPCS over production code and tests, and PHPStan |
| `format:php`, `format:php:tests` | Auto-fix code style (PHPCBF) |
| `quality-check` | Manifest and changelog validation, lint, Unit and Integration tests |
| `changelog:add`, `changelog:validate`, `changelog:write` | Stage a changelog fragment, check the staged fragments, or write them into `CHANGELOG.md` at release |

## Testing strategy

- **Unit tests** (`tests/Unit/`) run in plain PHP with no WordPress loaded, including tests that run PHPStan, read the package manifests, and check that each package README's examples are copied from its persona.
- **Integration tests** (`tests/Integration/`) run inside wp-env's `cli` container against real WordPress, hooks and users, on a single site with WooCommerce, and the `multisite` and `without-woocommerce` groups on a network without it. `test:integration` starts the tests environment before every run, which also applies any change to its config; `test:integration:run` skips that start, as CI does after starting the environment itself.
- **Fixture plugins** (`tests/Fixtures/consumer-a/`, `tests/Fixtures/consumer-b/`): consumer-a is a WooCommerce plugin built on all five packages. It installs them through its own `composer.json`, scopes them with wordpress-configs and passes PHPStan on its own code; the Integration tests copy its production build into the plugins directory and run it through wp-admin requests and WP-CLI, whole and with files missing. consumer-b, built the same way on every package but woocommerce, boots where WooCommerce is absent and beside consumer-a, activated and loaded in either order; the tests also check that each scoped build holds exactly its closure and autoloads its own prefixed copy.
- **Personas** (`tests/Fixtures/personas/`): one per stack, each requiring only the top package of its stack and built like the fixture plugins, so each production build holds exactly its scoped closure and nothing unscoped. The shared persona runs in plain PHP; the core persona boots its components and runs a migration, and the settings persona saves and rejects a value through `options.php`, both where WooCommerce is absent; the woocommerce persona saves a settings tab and product data through WooCommerce's screens and logs to WooCommerce's log; the bootstrap persona runs on PHP 7.4, which many sites still run.
- **Prefer-lowest lane** (`tests/bin/prefer-lowest.sh`): consumer-a runs with its runtime packages locked at the lowest versions its constraints allow, so a minimum too low to work fails before a consumer installs it. For the core, settings and woocommerce personas, which like every persona list their stack's mirrors as `vcs` repositories, the script keeps only the tested package on the path repository and locks every other framework package from its mirror at the lowest allowed tag, so they run only where the mirrors carry release tags.
- **CI** runs the Integration tests once for the newest release of every WordPress branch from the floor up, read from WordPress's version API, so a new release joins without a change here; WordPress trunk runs nightly, outside pull requests.
- **PHP 7.4 tests** (`tests/Php74/`) run with the host's PHP against a PHP 7.4 site with WordPress 6.3, through WP-CLI in its environment: the bootstrap persona passes its floors there, and a copy that needs a newer PHP shows WordPress core's message through the notice markup WordPress before 6.4 needs, both with the updater hooked; consumer-a, below its floors there, keeps its safeguard's export lock.
- **Type tests** (`tests/PHPStan/`) are files PHPStan analyzes during `lint:php`: `assertType()` calls pin inferred types, and each expected error carries a `@phpstan-ignore` naming its identifier, so the run fails when the error disappears.
