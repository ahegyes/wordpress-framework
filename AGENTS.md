# wordpress-framework

A WordPress plugin framework published as five Composer packages under `ahegyes/`, PHP namespace `DeepWebSolutions\Framework\`, GPL-2.0-or-later. splitsh splits each `packages/<dir>/` to its read-only `wp-framework-<dir>` mirror on every push to `trunk`; consumers install the mirrors through `vcs` repositories, not Packagist, and prefix them with php-scoper or Strauss. The packages form a shallow tree, not a chain: no framework package requires bootstrap, core never requires settings, and neither settings nor woocommerce requires core, so a plugin ships only its dependency closure.

## Layout

```
wordpress-framework/
├── packages/bootstrap/    ahegyes/wp-framework-bootstrap    php >=7.4 · no require · no autoload: loaded by path only
│   ├── load.php             require_once's requirements.php and updater.php   (path = public API)
│   ├── requirements.php     check_requirements( string $plugin_file ): bool
│   ├── updater.php          register_github_updater( string $plugin_file ): void
│   └── uninstall.php        uninstall( string $plugin_file, array $footprint, string $opt_in_option, string $opt_in_key = '', ?callable $cleanup = null ): void
├── packages/shared/       ahegyes/wp-framework-shared       php >=8.5, ext-filter · no package require · WordPress-free
│   └── src/               psr-4 DeepWebSolutions\Framework\Shared\ ; files: functions.php · Exception/, Error/, Result/, ValueObject/, Version/
├── packages/core/         ahegyes/wp-framework-core         php >=8.5 · shared ^2.0, psr/container ^2, psr/log ^3
│   └── src/               psr-4 DeepWebSolutions\Framework\
│       ├── ComponentInterface, CompositeComponentInterface, ConditionalComponentInterface
│       ├── PluginKernel, PluginBoot, MigrationRunner
│       └── Utilities/{NoticeQueue, ErrorLogLogger}
├── packages/settings/     ahegyes/wp-framework-settings     php >=8.5 · shared ^2.0
│   └── src/               psr-4 DeepWebSolutions\Framework\Settings\ ; files: functions.php (render_control, sanitize_value, verify_object_form, is_valid_key) · OptionsPage
├── packages/woocommerce/  ahegyes/wp-framework-woocommerce  php >=8.5 · settings ^2.0, shared ^2.0, psr/log ^3 (WC ^11.1 as host)
│   ├── bootstrap/uninstall.php  PHP 7.4-parseable, by path: delete_order_meta( array $meta_keys ): void
│   └── src/               psr-4 DeepWebSolutions\Framework\WooCommerce\ ; files: Settings/functions.php
│       ├── WooCommerceRequirement, WooCommerceLogger
│       ├── Settings/{SettingsTab, SettingsSection, functions.php (validate_option)}
│       └── ProductData/ProductDataTab
├── tests/{Unit, PHPStan, Integration, Fixtures/{consumer-a (WC), consumer-b (non-WC), personas/{bootstrap,shared,core,settings,woocommerce}}}
├── phpcs.dist.xml         PHPCS over production code; phpcs.tests.dist.xml lints tests/
├── phpstan.shared.neon    PHPStan over packages/shared and tests/PHPStan/Shared, with no WordPress symbol known
├── phpstan.wordpress.neon PHPStan over packages/core, with the WordPress stubs and extensions
└── composer.json          one `packages/*` path repository
```

## Commands

```sh
composer packages-install
composer quality-check          # PHPCS (both profiles), PHPStan, Unit tests
composer test:unit
composer validate --strict
composer audit --locked
npm audit --omit=dev --audit-level=high
actionlint
zizmor .github/
```

## Conventions

1. **Gates and toggles.** `should_load()` is static, pure and total: no hooks, writes, notices, translation, user reads or construction. Read options with an explicit default. Detect companions by include-time constants, classes or functions, aware of network activation. A feature's toggle and settings UI live outside its gate; a gate mixing an environment check with a setting exposes the environment half as a static method.
2. **Nodes and the container.** Node classes never extend, implement or `use` optional or late-loaded types. Never constructor-inject a gated component; inject ungated services. Definitions are `static fn ( ContainerInterface $c )` closures or autowiring, never PHP-DI helpers. Hold the built container as `ContainerInterface`. Container reads belong in the kernel and composition wiring: a class-entry read checks the entry's type before use, and a factory collecting contributors re-applies their `should_load()` before resolving them. Framework classes take their logger injected; the composition root chooses the logger and whether `NoticeQueue` is a root.
3. **Constructors and `register_hooks()`.** Constructors take services only. `register_hooks()` adds `array( $this, 'method' )` hooks, or constructs an `OptionsPage` or `ProductDataTab` and calls its `register_hooks()`. It never fires actions. Registration-time hooks register unconditionally and authorize in the callback.
4. **Packages and `Shared\`.** Package edges stay as in the file map: settings and WC helpers never implement or import a core type, and a new edge needs the owner. A shared primitive is added only when it fills a gap PHP and its bundled extensions leave, references no WordPress or WooCommerce symbol and has a framework or certain-port call site; WordPress never enters the shared package. Result serves only an internal operation with two or more expected failure reasons a caller branches on, declared as `AbstractResult<T, E>` under `#[\NoDiscard]`; WordPress and WooCommerce APIs, filters, REST and AJAX responses and v1 public functions use `X|WP_Error`. Carriers are `final readonly` and extend `AbstractValueObject` only when something compares them. `Version` parses only versions the plugin controls, never on the boot path. Framework code throws the `Shared\Exception` mirrors.
5. **Safeguards.** Everything a safeguard calls lives in `includes/safeguards.php` or files it requires. Relaxation happens only through the safeguard's own filter, after `{prefix}_initialized`, on an explicit `false`.
6. **Shared logic.** Logic that global functions or templates need lives in plain functions or static methods. Per-request state lives in a static holder or `WC()->session`. No service locator.
7. **Migration steps.** Append-only; converge from any partial state; verify writes and throw on failure, including a missing role and an AS enqueue of `0` with no matching `as_has_scheduled_action()`. Queued is not migrated. Create resources resumably (lookup marker, explicit `post_author`). Use WC CRUD for orders and products. Each step finishes well under 10 minutes; bulk work is queued. Steps run on `wp_loaded`, so every `init` registration exists. One linear list serves every build variant.
8. **Settings.** Full keys are `Options` constants; WC ids are flat. Boot-time defaults are constants; translated defaults come from `Options::text()` after `init`. Validate on write, guard on read. Direct meta writes go through `wp_slash()`. WooCommerce multiselect option keys are truthy, because WooCommerce's save drops `'0'` and `''`.
9. **Object forms** render through `render_control()` and call `verify_object_form()`; every framework saver leaves absent (unrendered or disabled) fields untouched.
10. **Translation, by-path files, Plugin Check.** Never translate at include or registration time. Framework strings use only the `'wp-framework'` domain, at most four, owned by core, settings and woocommerce; bootstrap and shared ship none. The by-path files (the bootstrap package, the WC package's `bootstrap/`) stay 7.4-parseable, loaded by path, outside every `autoload` key; their paths are public API. Code is Plugin-Check-clean, using inline `phpcs:ignore` with a reason; local PHPCS runs the full `WordPress` standard, which covers the WordPress sniffs Plugin Check runs (its own `PluginCheck` sniffs and file checks run only at release).

## Style

- Every PHP file opens with `<?php declare( strict_types=1 );`, and every file outside the shared package carries `\defined( 'ABSPATH' ) || exit;` after its `use` block (a Composer `files` entry returns instead of exiting). Code uses `array()`, `protected` rather than `private`, `final` classes by default and `#[\Override]` on every override.
- Every element outside `tests/` has a docblock: one third-person sentence naming the role, such as "Returns …", plus a second paragraph only for a non-obvious why. Property and constant docblocks open with "The …", constructors read "Constructor.", and an override reads `{@inheritDoc}`. Tag values align at column 10, each `@param` stays on one line, `@throws` reads "Thrown when …", `@since` names the release that added the element, and `@version` the last release that changed its behavior or signature.
- Classes and traits outside `tests/` group members in `// region NAME` blocks named, in order, TRAITS, FIELDS AND CONSTANTS, MAGIC METHODS, GETTERS, SETTERS, INHERITED METHODS, METHODS, FACTORY METHODS, HOOKS and HELPERS; exception classes, interfaces and enums carry none.
- Inline comments state a non-obvious why in one capitalized sentence, and every file describes the present, never the change. A `phpcs:ignore` trails its line, names the sniff and gives a one-clause reason; a `@phpstan-ignore` names its identifier.
- Tests are `final` classes named after what they cover, with `test_snake_case` methods, `#[DataProvider]` keys in kebab-case, real instances and recording spies, and no docblocks.
- Markdown keeps one line per paragraph, sentence-case headings and an impersonal, present-tense voice.
- Commit subjects are capitalized imperatives of 72 characters or fewer, with no `type(scope):` prefix and no trailing period. The body says what was wrong and why before what changed, and an agent-assisted commit ends with `Assisted-by: <agent>:<model-id>`, never `Co-Authored-By`.

## Limitations

1. A throw in `register_hooks()` leaves earlier hooks live, and side effects are never undone. Safeguards still ignore relaxations, because `_initialized` never fires.
2. Gates evaluate once, at `plugins_loaded`. Schema-gated components switch on one request after the migrating request.
3. A request killed mid-migration holds the lock for 10 minutes, then the step re-runs. One primary database is assumed. While a failure persists, each request issues one `INSERT IGNORE` plus one conditional `UPDATE`.
4. After a rollback, older code runs on newer data until the plugin is upgraded again; the written-back revision then re-runs the newer steps. Mixed-version rolling deploys may re-run steps, which converge.
5. Uninstall cleans only the current site. Other sites and network-global user meta keep their data. AS actions stay queued if AS is not loaded at uninstall.
6. Over WC REST, `validate` receives WooCommerce's normalized value: unknown multiselect choices are already dropped and text already sanitized. A rejected value is not stored, but the response echoes it. Grouped `option[key]` ids would bypass the bridge, hence flat ids.
7. WC-rendered controls carry WooCommerce's markup. The framework guarantees markup only for `render_control()`.
8. Partial bootstrap package: if `load.php` exists but a sibling it requires is missing, the `Error` is thrown before the template's catch and the plugin fatals at include. A missing `vendor-prefixed/` also unregisters the updater and skips the floor gate, so safeguards then load on whatever PHP runs (two faults).
9. Deactivation, recovery mode and below-floor runtimes unload safeguards, as for any plugin. Plugins whose data would leak (IC) hide it at the data level.
10. Concurrent requests by one user can drop a queued flash notice.
