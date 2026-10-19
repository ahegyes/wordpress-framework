# wordpress-framework

A WordPress plugin framework published as five Composer packages under `ahegyes/`, PHP namespace `DeepWebSolutions\Framework\`, GPL-2.0-or-later. splitsh splits each `packages/<dir>/` to its read-only `wp-framework-<dir>` mirror on every push to `trunk`; consumers install the mirrors through `vcs` repositories, not Packagist, and prefix them with php-scoper or Strauss. The packages form a shallow tree, not a chain: no framework package requires bootstrap, core never requires settings, and neither settings nor woocommerce requires core, so a plugin ships only its dependency closure.

## Layout

```
wordpress-framework/
├── packages/bootstrap/    ahegyes/wp-framework-bootstrap    php >=7.4 · no require · no autoload: loaded by path only
│   ├── load.php             require_once's src/Requirements.php and src/Updater.php   (path = public API)
│   ├── uninstall.php        require_once's src/Uninstaller.php   (path = public API)
│   └── src/
│       ├── Requirements     ::check( string $plugin_file ): bool
│       ├── Updater          ::register( string $plugin_file ): void
│       └── Uninstaller      ::run( string $plugin_file, array $footprint, array $args = array() ): void
├── packages/shared/       ahegyes/wp-framework-shared       php >=8.5 · no package require · WordPress-free
│   └── src/               psr-4 DeepWebSolutions\Framework\Shared\ · Exception/, Error/, Result/, ValueObject/, Version/
├── packages/core/         ahegyes/wp-framework-core         php >=8.5, ext-filter · shared ^2.0, psr/clock ^1, psr/container ^2, psr/log ^3
│   └── src/               psr-4 DeepWebSolutions\Framework\
│       ├── ComponentInterface, CompositeComponentInterface, ConditionalComponentInterface, BatchedMigrationStepInterface
│       ├── PluginKernel, PluginBoot, MigrationRunner
│       ├── Notices/{AdminNotices, Notice, NoticeType, NoticeScope, NoticeContext, NoticeIdentity, StoredNotice, RecipientState, NoticeStoreInterface, RecipientStateStoreInterface, NoticeSelector (@internal), Stores/ (@internal)}
│       ├── Scheduling/{SchedulerInterface, Scheduler, ScheduledHook, Recurrence, Backends/ (@internal)}
│       ├── Storage/{OptionRows, OptionRow, MetaRows, MetaRow}
│       └── Utilities/{ErrorLogLogger, SystemClock}
├── packages/settings/     ahegyes/wp-framework-settings     php >=8.5 · shared ^2.0
│   └── src/               psr-4 DeepWebSolutions\Framework\Settings\ · a final class with render_control, sanitize_value, verify_object_form · OptionsPage
├── packages/woocommerce/  ahegyes/wp-framework-woocommerce  php >=8.5 · settings ^2.0, shared ^2.0, psr/log ^3 (WC ^11.2 as host)
│   ├── bootstrap/uninstall.php  PHP 7.4-parseable, by path: WooCommerceUninstaller::get_handlers(): array
│   └── src/               psr-4 DeepWebSolutions\Framework\WooCommerce\
│       ├── WooCommerceRequirement, WooCommerceLogger
│       ├── Settings/{SettingsTab, SettingsSection, a final class with validate_option}
│       └── ProductData/{ProductDataTab, a final class with get_value}
├── tests/{Unit, PHPStan, Integration, Fixtures/{consumer-a (WC), consumer-b (non-WC), personas/{bootstrap,shared,core,settings,woocommerce}}}
├── phpcs.dist.xml         PHPCS over production code; phpcs.tests.dist.xml lints tests/
├── phpstan.shared.neon    PHPStan over packages/shared and tests/PHPStan/Shared, with no WordPress symbol known
├── phpstan.wordpress.neon PHPStan over packages/core, with the WordPress stubs and extensions
├── phpstan.bootstrap.neon PHPStan over the by-path files at PHP 7.4 and WordPress 5.2, the oldest they run on
├── .wp-env.json           developer environment: single site with WooCommerce, as local, on port 8800
├── .wp-env.tests.json     Integration environment: single site with WooCommerce, as production
├── .wp-env.multisite.json Integration environment for the `multisite` and `without-woocommerce` groups: a network without WooCommerce, as production
└── composer.json          one `packages/*` path repository
```

## Commands

```sh
composer packages-install
composer quality-check          # validation, PHPCS (both profiles), PHPStan, Unit and Integration tests
composer test:unit
composer test:integration       # starts .wp-env.tests.json, then runs test:integration:run
composer test:integration:run   # Integration tests in the running .wp-env.tests.json, without the multisite and without-woocommerce groups; WP_ENVIRONMENT_TYPE=production
composer test:integration:multisite      # starts .wp-env.multisite.json, then runs test:integration:multisite:run
composer test:integration:multisite:run  # the multisite and without-woocommerce groups in the running .wp-env.multisite.json
composer audit --locked
npm audit --omit=dev --audit-level=high
actionlint
zizmor .github/
```

## Conventions

1. **Gates and toggles.** `should_load()` is static, pure and total: no hooks, writes, notices, translation, user reads or construction. Read options with an explicit default. Detect companions by include-time constants, classes or functions, aware of network activation. A feature's toggle and settings UI live outside its gate; a gate mixing an environment check with a setting exposes the environment half as a static method.
2. **Nodes and the container.** Node classes never extend, implement or `use` optional or late-loaded types. Never constructor-inject a gated component; inject ungated services. Definitions are `static fn ( ContainerInterface $c )` closures or autowiring, never PHP-DI helpers. Hold the built container as `ContainerInterface`. Container reads belong in the kernel and composition wiring: a class-entry read checks the entry's type before use, and a factory collecting contributors re-applies their `should_load()` before resolving them. Framework classes take their logger injected; the composition root chooses the logger.
3. **Constructors and `register_hooks()`.** Constructors take services only. `register_hooks()` adds `array( $this, 'method' )` hooks, or constructs an `OptionsPage` or `ProductDataTab` and calls its `register_hooks()`. It never fires actions. Registration-time hooks register unconditionally and authorize in the callback. Scheduled-hook callbacks register on every request, because Action Scheduler runs actions in admin-ajax, WP-Cron and WP-CLI requests, and tolerate a duplicate or a missing invocation.
4. **Packages and `Shared\`.** Package edges stay as in the file map: settings and WC helpers never implement or import a core type, and a new edge needs the owner. Every reachable `Shared\` symbol is API, and WordPress never enters the shared package. A primitive enters only when it fills a gap that PHP, its extensions and PHPStan's typing leave at its call sites, references no WordPress or WooCommerce symbol, is called by two framework packages or by two certain-port plugins each more than once, and has its siblings decided with it; until then, the classes that need it keep protected copies. A kernel type enters only when it would work unchanged in any PHP project, and on the owner's ruling. Result serves only an internal operation with two or more expected failure reasons a caller branches on, declared as `AbstractResult<T, E>` under `#[\NoDiscard]`; WordPress and WooCommerce APIs, filters, REST and AJAX responses and v1 public functions use `X|WP_Error`. Carriers are `final readonly` and extend `AbstractValueObject` only when something compares them. `Version` parses only versions the plugin controls, never on the boot path. Framework code throws the `Shared\Exception` mirrors.
5. **Safeguards.** Everything a safeguard calls lives in `includes/safeguards.php` or files it requires. Relaxation happens only through the safeguard's own filter, after `{prefix}_initialized`, on an explicit `false`.
6. **Shared logic.** Logic that global functions or templates need lives in static methods of final classes. Per-request state lives in a static holder or `WC()->session`. No service locator.
7. **Migration steps.** Append-only; a retired step keeps its code or throws. Converge from any partial state; verify writes and throw on failure, including a missing role and a scheduler write that returns `WP_Error`. Create resources resumably (lookup marker, explicit `post_author`). Use WC CRUD for orders and products. A plain step finishes well under 10 minutes; bulk work is a `BatchedMigrationStepInterface` step, run only from the runner's scheduled hook, at about 1–2 s per batch. Steps run on `wp_loaded`, so every `init` registration exists. One linear list serves every build variant.
8. **Settings.** Full keys are `Options` constants; WC ids are flat. Boot-time defaults are constants; translated defaults come from `Options::text()` after `init`. Validate on write, guard on read. Direct meta writes go through `wp_slash()`. Framework components store data only under the consumer's `{prefix}_`, so the plugin's footprint covers it. The bootstrap updater's release caches carry the text domain instead, and the uninstall engine deletes them itself. WooCommerce multiselect option keys are truthy, because WooCommerce's save drops `'0'` and `''`.
9. **Object forms** render through `render_control()` and call `verify_object_form()`; every framework saver leaves absent (unrendered or disabled) fields untouched.
10. **Translation, by-path files, Plugin Check.** Never translate at include or registration time. Framework strings use only the `'wp-framework'` domain, at most five, owned by core, settings and woocommerce; bootstrap and shared ship none. The by-path files (the bootstrap package, the WC package's `bootstrap/`) stay 7.4-parseable, loaded by path, outside every `autoload` key, and define code only as final classes with public static entry points; consumers require only the loader files, whose paths are public API. Code is Plugin-Check-clean, using inline `phpcs:ignore` with a reason; local PHPCS runs the full `WordPress` standard, which covers the WordPress sniffs Plugin Check runs (its own `PluginCheck` sniffs and file checks run only at release).

## Contract

The 2.x contract is every public class, interface, enum, method and constant, the protected members of non-final classes, and the hook and priority each public callback method is registered on, unless tagged `@internal`. `@internal`, `private` and protected-on-final members are not contract. Hook names and their arguments, option and meta keys, REST routes and the by-path loader paths are contract too.

A package README may name namespaces whose symbols are internal, and a class there is internal even without its tag; core's are `Notices\Stores\` and `Scheduling\Backends\`.

Releases follow SemVer on this contract from the first tag: a break of it ships as the next major, and internals change in any release.

## Style

- Every PHP file opens with `<?php declare( strict_types=1 );`. Every package directory holds the stub `index.php`, `<?php declare( strict_types=1 ); // Silence is golden.`, and every other file outside the shared package carries `\defined( 'ABSPATH' ) || exit;` after its `use` block. Code uses `array()`, `final` classes by default and `#[\Override]` on every override, and `protected` rather than `private`, except for the internals of a non-final class, which are `private` because its protected members are contract.
- No package ships free functions or a Composer `files` entry. A function family is a final class with public static entry points and protected static helpers, an internal several classes call is copied into each as a protected member, and a class with only static members declares a protected constructor. The by-path classes register hooks with static closures, since a named callback would have to be a public method.
- A name imported from `Psr\` takes a `Psr` alias, as in `use Psr\Log\LoggerInterface as PsrLoggerInterface;`, so every use shows that the type is a PSR interface or class.
- Every element outside `tests/` has a docblock: one third-person sentence naming the role, such as "Returns …", plus a second paragraph only for a non-obvious why. Property and constant docblocks open with "The …", constructors read "Constructor.", and an override reads `{@inheritDoc}`. Tag values align at column 10, a file docblock's `@package` sits in its own group after `@since` and `@version`, each `@param` stays on one line, `@throws` reads "Thrown when …", `@since` names the release that added the element, and `@version` the last release that changed its behavior or signature.
- Every `do_action()` and `apply_filters()` with a public hook name carries a WordPress hook docblock directly above the call: "Fires …" or "Filters …", `@since`, `@version` and one `@param` per argument, which PHPStan checks against the call's arguments.
- Classes and traits outside `tests/` group members in `// region NAME` blocks named, in order, TRAITS, FIELDS AND CONSTANTS, MAGIC METHODS, GETTERS, SETTERS, INHERITED METHODS, METHODS, FACTORY METHODS, HOOKS and HELPERS; exception classes, interfaces and enums carry none.
- Inline comments state a non-obvious why in one capitalized sentence, and every file describes the present, never the change. A `phpcs:ignore` trails its line, names the sniff and gives a one-clause reason; a `@phpstan-ignore` names its identifier.
- Tests are `final` classes named after what they cover, with `test_snake_case` methods, `#[DataProvider]` keys in kebab-case, real instances and recording spies, and no docblocks.
- Markdown keeps one line per paragraph, sentence-case headings and an impersonal, present-tense voice.
- Commit subjects are capitalized imperatives of 72 characters or fewer, with no `type(scope):` prefix and no trailing period. The body says what was wrong and why before what changed, and an agent-assisted commit ends with `Assisted-by: <agent>:<model-id>`, never `Co-Authored-By`.

## Limitations

1. A throw in `register_hooks()` leaves earlier hooks live, and side effects are never undone. Safeguards still ignore relaxations, because `_initialized` never fires.
2. Gates evaluate once, at `plugins_loaded`. Schema-gated components switch on one request after the migrating request.
3. A request killed mid-migration holds the lock for 10 minutes, then the step re-runs and the error row names the step that did not finish. One primary database is assumed. While a failure persists, each request issues one `INSERT IGNORE` plus one conditional `UPDATE`.
4. After a rollback, older code runs on newer data until the plugin is upgraded again; the written-back revision then re-runs the newer steps, and `{option}_high_water` keeps the highest revision reached. Mixed-version rolling deploys may re-run steps, which converge.
5. Uninstall cleans the current site; with `network` it cleans every site that consents, except on a large network, where it logs that it cleaned the current site only. Network-global user meta goes only on a single site or, outside a large network, on the consent constant. Action Scheduler actions stay queued when it is not loaded at uninstall, and fail once it runs them, since no callback is registered; a job that cannot be unscheduled is logged and left.
6. Over WC REST, `validate` receives WooCommerce's normalized value: unknown multiselect choices are already dropped and text already sanitized. A rejected value is not stored, but the response echoes it. Grouped `option[key]` ids would bypass the bridge, hence flat ids.
7. WC-rendered controls carry WooCommerce's markup. The framework guarantees markup only for `render_control()`.
8. Partial bootstrap package: if `load.php` exists but a file it requires is missing, the `Error` is thrown before the template's catch and the plugin fatals at include. A missing `vendor-prefixed/` also unregisters the updater and skips the floor gate, so safeguards then load on whatever PHP runs (two faults).
9. Deactivation, recovery mode and below-floor runtimes unload safeguards, as for any plugin. Plugins whose data would leak (IC) hide it at the data level.
10. Scheduling and one-shot notices are best effort. Either scheduler can run an invocation twice or not at all, and work advances only with site traffic, Action Scheduler's admin dispatch, or a system cron or WP-CLI runner. On hosts that replace WP-Cron's store, the WP-Cron backend still reads only the `cron` option, so it may not see the host's events. Action Scheduler compares hook names and arguments under the database collation, so identities that differ only in letter case are one there. Two concurrent renders can both show a one-shot notice, and a request that dies after output shows it again; notices wait out block-editor screens, which hide them.
11. Update offers come only from WordPress's own plugin update check. While its wordpress.org request fails, the check offers no Update URI release, so a new release waits for a later check that succeeds. A plugin active only on sites other than the main one is never offered a release, because WordPress checks only on the main site and in the network admin.
